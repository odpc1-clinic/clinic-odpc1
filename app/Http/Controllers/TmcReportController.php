<?php

namespace App\Http\Controllers;

use App\Models\VisitVaccine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * รายงาน TMC รายเดือน — แทนการนับมือลงชีท "สรุปจำนวนการให้บริการ" (TMC_odpc1_2026)
 *
 * วัคซีนนับจาก "บันทึกการฉีดวัคซีน" (visit_vaccines) ไม่ใช่จากใบสั่งยา
 * เพราะหมอยังไม่ได้สั่งวัคซีนผ่านระบบทุกเคส แต่พยาบาลลงการฉีดทุกเข็ม (ผู้ใช้ยืนยัน 2569-11-03)
 * นับเฉพาะที่คลินิกให้ (ฉีดที่นี่ + จ่ายให้ไปทานเอง) — ประวัติจากที่อื่นไม่ใช่งานบริการของเรา
 *
 * visit_vaccines.vaccinated_date เก็บ ค.ศ. ส่วน visits.date เก็บ พ.ศ. — ต้องแปลงช่วงคนละแบบ
 */
class TmcReportController extends Controller
{
    /**
     * กลุ่มวัคซีนตามลำดับในชีทเดิม — จับจากชื่อ (snapshot ตอนฉีด + ชื่อในแคตตาล็อก)
     * ลำดับในอาร์เรย์สำคัญ: ตัวที่ชื่อซ้อนกันต้องมาก่อน (Men B ก่อน ACYW, Twinrix ก่อน HepA/B, Tdap ก่อน dT)
     * ไม่เข้ากลุ่มไหน = แสดงแยกตามชื่อจริง ไม่ทิ้ง
     */
    private const GROUPS = [
        'yf'      => ['ไข้เหลือง (Yellow fever)',                '/yellow\s*fever|stamaril|ไข้เหลือง/iu'],
        'menb'    => ['ไข้กาฬหลังแอ่น group B',                  '/group\s*b|type\s*b|bexsero/iu'],
        'menacwy' => ['ไข้กาฬหลังแอ่น gr.A,C,Y,W-135',            '/mening|ไข้กาฬหลังแอ่น|menactra|menveo|nimenrix/iu'],
        'flu'     => ['ไข้หวัดใหญ่ (Influenza)',                  '/influenza|ไข้หวัดใหญ่|\bflu/iu'],
        'cholera' => ['อหิวาตกโรค (Cholera)',                     '/cholera|อหิวาต|euvichol|dukoral/iu'],
        'rabies'  => ['พิษสุนัขบ้า (Rabies)',                     '/rabies|พิษสุนัขบ้า|chirorab|verorab|speeda/iu'],
        'dengue'  => ['ไข้เลือดออก (Dengue)',                     '/dengue|ไข้เลือดออก|qdenga/iu'],
        'twinrix' => ['ไวรัสตับอักเสบเอ+บี (Twinrix)',            '/twinrix|hepa\s*\+\s*b/iu'],
        'hepa'    => ['ไวรัสตับอักเสบเอ (Hepatitis A)',           '/havrix|avaxim|hepatitis\s*a|ตับอักเสบเอ/iu'],
        'hepb'    => ['ไวรัสตับอักเสบบี (Hepatitis B)',           '/hepatitis\s*b|engerix|ตับอักเสบบี/iu'],
        'hpv9'    => ['HPV-9',                                    '/hpv\s*-?9|gardasil\s*-?9|9 สายพันธ/iu'],
        'hpv4'    => ['HPV-4',                                    '/hpv\s*-?4|gardasil\s*-?4|4 สายพันธ/iu'],
        'hpv'     => ['มะเร็งปากมดลูก (HPV ไม่ระบุสายพันธุ์)',     '/hpv|gardasil|มะเร็งปากมดลูก/iu'],
        'je'      => ['ไข้สมองอักเสบ (JE)',                       '/\bJE\b|japanese|สมองอักเสบ|imojev/iu'],
        'typhoid' => ['ไทฟอยด์ (Typhoid)',                        '/typhoid|typhim|ไทฟอยด์/iu'],
        'tdap'    => ['บาดทะยัก คอตีบ ไอกรน (Tdap)',              '/tdap|ไอกรน|boostagen|boostrix|adacel/iu'],
        'dt'      => ['คอตีบ บาดทะยัก (dT)',                      '/\bdT\b|คอตีบ|บาดทะยัก|tetanus/u'],
        'mmr'     => ['หัด หัดเยอรมัน คางทูม (MMR)',              '/mmr|หัด/iu'],
        'var'     => ['อีสุกอีใส (Varicella)',                    '/varicella|varivax|อีสุกอีใส/iu'],
        'zoster'  => ['งูสวัด (Zoster)',                          '/zoster|shingrix|งูสวัด/iu'],
        'rsv'     => ['RSV',                                      '/\bRSV\b|arexvy|abrysvo/iu'],
        'pcv'     => ['PCV 20',                                   '/pcv|prevenar|pneumo|ปอดอักเสบ/iu'],
        'polio'   => ['โปลิโอ (Polio)',                           '/polio|ipv|โปลิโอ/iu'],
    ];

    public static function groupOf(?string ...$names): string
    {
        $hay = implode(' | ', array_filter($names));
        foreach (self::GROUPS as $key => [, $re]) {
            if (preg_match($re, $hay)) { return $key; }
        }
        return '';
    }

    public function index(Request $request)
    {
        // ค่าตั้งต้น = เดือนที่แล้ว เพราะรายงานทำหลังปิดเดือน
        $ym = (string) $request->get('m', '');
        $start = preg_match('/^\d{4}-\d{2}$/', $ym)
            ? Carbon::createFromFormat('Y-m-d', $ym . '-01')->startOfDay()
            : now()->subMonthNoOverflow()->startOfMonth();
        if ($start->year > 2400) { $start->subYears(543); }   // เผื่อมีคนพิมพ์ปี พ.ศ. มาใน URL
        $end = $start->copy()->endOfMonth();

        [$beFrom, $beTo, $visits, $shots, $takeHome] = $this->monthData($start, $end);

        $people = [
            'visit'  => $this->people($visits),
            'vaccine'=> $this->people($shots),
        ];
        $visitCount = $visits->count();

        // ---- สรุปตามวัคซีน ----
        $rows = [];
        foreach ($shots as $s) {
            $g = self::groupOf($s->name, $s->mname);
            $key = $g ?: ('x:' . (trim((string) $s->name) ?: '(ไม่ระบุชื่อ)'));
            $label = $g ? self::GROUPS[$g][0] : (trim((string) $s->name) ?: '(ไม่ระบุชื่อ)');
            $s->group_label = $label;
            $rows[$key] ??= ['label' => $label, 'doses' => 0, 'people' => [], 'byDose' => [], 'known' => (bool) $g];
            $rows[$key]['doses']++;
            $rows[$key]['people'][$s->patient_id] = true;
            $d = $s->dose_number ? (string) (int) $s->dose_number : '?';
            $rows[$key]['byDose'][$d] = ($rows[$key]['byDose'][$d] ?? 0) + 1;
        }
        // เรียงตามลำดับชีทก่อน ตามด้วยตัวที่ไม่รู้จักตามชื่อ
        $order = array_flip(array_keys(self::GROUPS));
        uksort($rows, fn ($a, $b) => [($order[$a] ?? 999), $a] <=> [($order[$b] ?? 999), $b]);
        foreach ($rows as &$r) { ksort($r['byDose'], SORT_NATURAL); $r['people'] = count($r['people']); }
        unset($r);

        // แถวตามชีทเดิม (TMC_odpc1 ชีทรายเดือน คอลัมน์ AQ) เรียงเหมือนกันทุกบรรทัด เป็น 0 ก็ต้องโชว์
        // เพื่อให้กด "คัดลอกตัวเลข" แล้ววางทับคอลัมน์ AR ได้ตรงแถว
        $sheet = [];
        $get = fn ($k) => $rows[$k] ?? ['doses' => 0, 'people' => 0, 'byDose' => []];
        $sheet[] = ['จำนวน dose วัคซีน', $shots->count() + array_sum($takeHome), 'โดส'];
        foreach (['yf' => 'ไข้เหลือง', 'menacwy' => 'ไข้กาฬหลังแอ่น gr.A,C,Y,W-135', 'menb' => 'ไข้กาฬหลังแอ่น group B', 'flu' => 'ไข้หวัดใหญ่'] as $k => $label) {
            $sheet[] = [$label, $get($k)['doses'], 'โดส'];
        }
        $ch = $get('cholera');
        $sheet[] = ['อหิวาตกโรค (dose 1)', $ch['byDose']['1'] ?? 0, 'โดส'];
        $sheet[] = ['อหิวาตกโรค (dose 2)', ($ch['byDose']['2'] ?? 0) + array_sum($takeHome), 'โดส'];
        $choleraPeople = $shots->filter(fn ($s) => self::groupOf($s->name, $s->mname) === 'cholera')->pluck('patient_id')
            ->merge(array_keys($takeHome))->unique()->count();
        $sheet[] = ['อหิวาตกโรค (คน)', $choleraPeople, 'คน'];
        $sheetKeys = ['rabies' => 'RABIES VACCINE', 'dt' => 'dT', 'tdap' => 'Tdap', 'hpv4' => 'HPV-4', 'hpv9' => 'HPV-9',
            'twinrix' => 'Hepatitis A&B (Twinrix)', 'hepa' => 'Hepatitis A', 'hepb' => 'Hepatitis B', 'je' => 'JE',
            'dengue' => 'DENGUE VACCINE', 'mmr' => 'MMR', 'rsv' => 'RSV', 'zoster' => 'Recombinant Zoster',
            'typhoid' => 'Typhoid', 'pcv' => 'PCV 20'];
        foreach ($sheetKeys as $k => $label) {
            $sheet[] = [$label, $get($k)['doses'], 'โดส'];
        }
        // วัคซีนที่ชีทไม่มีช่อง ต่อท้าย (มีเฉพาะเดือนที่ฉีดจริง) — ทำให้รวมแต่ละแถวได้เท่ายอด dose ทั้งหมด
        $inSheet = array_merge(['yf', 'menacwy', 'menb', 'flu', 'cholera'], array_keys($sheetKeys));
        foreach ($rows as $k => $r) {
            if (!in_array($k, $inSheet, true)) { $sheet[] = [$r['label'] . ' (ไม่มีช่องในชีท)', $r['doses'], 'โดส']; }
        }
        // ขอรับคำปรึกษา = คนที่เปิดงาน TMC เดือนนี้ — ได้ฉีด / ไม่ได้ฉีดเลยทั้งเดือน
        $vaccinated = $shots->pluck('patient_id')->unique()->flip();
        $visitPeople = $visits->pluck('patient_id')->unique();
        $consultOnly = $visitPeople->reject(fn ($id) => isset($vaccinated[$id]))->count();
        $sheet[] = ['ขอรับคำปรึกษาอย่างเดียว (ไม่ได้ฉีด)', $consultOnly, 'คน'];
        $sheet[] = ['ขอรับคำปรึกษา และ ฉีดวัคซีน', $visitPeople->count() - $consultOnly, 'คน'];

        $book = $this->bookStats($start, $end, $shots);
        $sheet[] = ['ใช้สมุดเล่มเดิม', $book['old'], 'เล่ม'];
        // ชำรุด + เสีย รวมแถวเดียว (เล่มอยู่ในมือ ส่งทำลาย) · หาย แยกอีกแถว — คลินิกขอ 2569-11-04
        // ทดแทนที่หาเหตุผลไม่เจอ (ประวัติถูกลบ) นับในแถวหาย เพราะไม่มีเล่มให้ทำลาย
        $kinds = array_count_values(array_column($book['events'], 'kind'));
        $sheet[] = ['สมุดชำรุด/เสีย', ($kinds['damaged'] ?? 0) + ($kinds['spoiled'] ?? 0), 'เล่ม'];
        $sheet[] = ['สมุดหาย', ($kinds['lost'] ?? 0) + ($kinds['replace'] ?? 0), 'เล่ม'];
        $sheet[] = ['รวมสมุดที่ออกใหม่', $book['issued'], 'เล่ม'];
        $sheet[] = ['รวมสมุดที่ใช้ไป', $book['issued'] + $book['spoiled'], 'เล่ม'];

        $noDose = $shots->filter(fn ($s) => !$s->dose_number)->count();

        $director = $this->director($visits, $shots, $takeHome, $beFrom, $beTo);
        $dest = $this->destStats($visits);

        return view('report.tmc_monthly', compact('start', 'end', 'people', 'visitCount', 'rows', 'sheet', 'shots', 'noDose', 'takeHome', 'book', 'director', 'dest'));
    }

    /** ข้อมูลดิบของเดือน: [วันแรก พ.ศ., วันสุดท้าย พ.ศ., visit TMC, เข็มที่คลินิกให้, อหิวาต์ทานเองแบบเก่า (patient_id => โดส)] */
    /**
     * ประเทศปลายทางของ visit TMC ในเดือน (2569-11-04) — ไว้กรองดูรายชื่อตามประเทศ
     * คนเดียวไปหลายประเทศ นับในทุกประเทศที่ไป ยอดรวมทุกแถวจึงมากกว่าจำนวนคนได้
     */
    private function destStats($visits): array
    {
        $ready = \App\Models\Visit::destCountriesReady();
        $rows = []; $none = 0; $list = [];
        foreach ($visits as $v) {
            $names = $ready ? \App\Models\Visit::destCountryNames($v->tmc_dest_countries ?? null) : [];
            if (!$names) { $none++; }
            foreach ($names as $code => $th) {
                $rows[$code] ??= ['name' => $th, 'visits' => 0, 'people' => []];
                $rows[$code]['visits']++;
                $rows[$code]['people'][$v->patient_id] = true;
            }
            $list[] = ['id' => $v->id, 'date' => $v->date, 'code' => $v->code, 'name' => trim($v->pname . ' ' . $v->surname),
                'countries' => $names, 'text' => (string) $v->tmc_destinations];
        }
        foreach ($rows as &$r) { $r['people'] = count($r['people']); }
        unset($r);
        uasort($rows, fn ($a, $b) => [$b['people'], $a['name']] <=> [$a['people'], $b['name']]);
        return ['ready' => $ready, 'rows' => $rows, 'none' => $none, 'list' => $list];
    }

    private function monthData(Carbon $start, Carbon $end): array
    {
        // ---- ผู้มารับบริการ TMC (ทุกครั้งที่เปิดงานคลินิก 3 ไม่ว่าจะฉีดหรือไม่) ----
        $beFrom = ($start->year + 543) . $start->format('-m-d');
        $beTo   = ($end->year + 543) . $end->format('-m-d');
        $visits = DB::table('visits as v')->join('patients as p', 'p.id', '=', 'v.patient_id')
            ->where('v.clinic', 3)->whereBetween('v.date', [$beFrom, $beTo])
            ->orderBy('v.date')->orderBy('v.id')
            ->get(array_merge(['v.id', 'v.patient_id', 'v.date', 'p.sex', 'p.nationality', 'v.tmc_vaccine_plan', 'v.tmc_travel_phase',
                    'p.code', 'p.name as pname', 'p.surname', 'v.tmc_destinations'],
                \App\Models\Visit::referReady() ? ['v.refer_hospital'] : [],
                \App\Models\Visit::destCountriesReady() ? ['v.tmc_dest_countries'] : []));

        // ---- เข็มที่คลินิกให้ในเดือน ----
        // ฉีดที่นี่ + จ่ายให้ไปทานเอง นับตาม "วันที่" บนรายการ (ทานเองคือวันที่ทาน)
        // ทานเข็ม 2 คนละเดือนกับที่มารับ = ไปอยู่เดือนที่ทาน (ผู้ใช้ขอ 2569-11-03)
        $shots = VisitVaccine::query()
            ->leftJoin('medicines as m', 'm.id', '=', 'visit_vaccines.medicine_id')
            ->join('patients as p', 'p.id', '=', 'visit_vaccines.patient_id')
            ->whereIn('visit_vaccines.source', [VisitVaccine::HERE, VisitVaccine::TAKE_HOME])
            ->whereBetween('visit_vaccines.vaccinated_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('visit_vaccines.vaccinated_date')->orderBy('visit_vaccines.id')
            ->get([
                'visit_vaccines.id', 'visit_vaccines.patient_id', 'visit_vaccines.visit_id', 'visit_vaccines.source',
                'visit_vaccines.name', 'visit_vaccines.dose_number', 'visit_vaccines.lot_number',
                'visit_vaccines.vaccinated_date', 'm.name as mname',
                'p.code', 'p.name as pname', 'p.surname', 'p.sex', 'p.nationality',
            ]);

        // รายการเก่า (ก่อนมีประเภท "จ่ายให้ไปทานเอง" 2569-11-03): อหิวาต์ dose 2 ที่ให้รับไปทานเอง ไม่มีบันทึกการฉีด
        // อยู่แค่ในแผนนัดของ visit ที่มารับ — นับตามวันนัดให้ทานในแผน (ให้ตรงกับรายการแบบใหม่)
        // ดูย้อน visit 3 เดือนก่อนหน้าด้วย เพราะวันทานอาจอยู่เดือนถัดจากวันที่มารับ
        // visit ที่ลงเป็น "จ่ายให้ไปทานเอง" แล้ว (เดือนไหนก็ตาม) ข้าม — ไม่งั้นนับซ้ำ
        // site=self = ปุ่ม "รับไปทานเอง" (2569-10-27) · ก่อนหน้านั้นพยาบาลใช้ "ฉีดที่อื่น" แล้วพิมพ์ว่ากินเอง
        $planFrom = $start->copy()->subMonthsNoOverflow(3);
        $planVisits = DB::table('visits')->where('clinic', 3)->whereNotNull('tmc_vaccine_plan')
            ->whereBetween('date', [($planFrom->year + 543) . $planFrom->format('-m-d'), $beTo])
            ->get(['id', 'patient_id', 'tmc_vaccine_plan']);
        $loggedTakeHome = VisitVaccine::where('source', VisitVaccine::TAKE_HOME)
            ->whereIn('visit_id', $planVisits->pluck('id')->all() ?: [0])->get(['visit_id', 'name'])
            ->filter(fn ($s) => self::groupOf($s->name) === 'cholera')->pluck('visit_id')->flip();
        $takeHome = [];
        foreach ($planVisits as $v) {
            if (isset($loggedTakeHome[$v->id])) { continue; }
            foreach (VisitVaccine::normalizePlan(json_decode((string) $v->tmc_vaccine_plan, true)) as $row) {
                $self = ($row['site'] ?? '') === 'self'
                    || (($row['site'] ?? '') === 'other' && preg_match('/กินเอง|ทานเอง|รับประทาน|ที่บ้าน/u', $row['place'] ?? ''));
                $day = VisitVaccine::toDbDate($row['date'] ?? '');
                if (! $self || ! $day || $day < $start->toDateString() || $day > $end->toDateString()) { continue; }
                foreach ($row['items'] as $it) {
                    if (self::groupOf($it['name']) === 'cholera') { $takeHome[$v->patient_id] = ($takeHome[$v->patient_id] ?? 0) + 1; }
                }
            }
        }

        return [$beFrom, $beTo, $visits, $shots, $takeHome];
    }

    /**
     * แบบรายงานผลการให้บริการคลินิกเวชศาสตร์การเดินทางฯ ส่ง ผอ. (ไฟล์ "รายงานTMCส่งผอ.69" — คลินิกขอ 2569-11-04)
     * แถวตามแบบฟอร์มเดิม แยก ชาวไทย / ชาวต่างชาติ / รวม
     * 'na' = ระบบไม่มีข้อมูลให้นับ (กรอกเอง) — ไม่ใส่ 0 กันเข้าใจผิดว่านับแล้วไม่มี
     */
    private function director($visits, $shots, array $takeHome, string $beFrom, string $beTo): array
    {
        $pid = fn ($rows) => collect($rows)->pluck('patient_id')->filter()->unique()->values();
        $natOf = [];
        foreach ($visits as $v) { $natOf[$v->patient_id] = [$v->nationality, (int) $v->sex]; }
        foreach ($shots as $s) { $natOf[$s->patient_id] = [$s->nationality, (int) $s->sex]; }
        $missing = array_diff(array_keys($takeHome), array_keys($natOf));
        if ($missing) {
            foreach (DB::table('patients')->whereIn('id', $missing)->get(['id', 'nationality', 'sex']) as $p) { $natOf[$p->id] = [$p->nationality, (int) $p->sex]; }
        }
        $isThai = function ($id) use ($natOf) {
            $n = strtoupper(trim((string) ($natOf[$id][0] ?? '')));
            return $n === '' ? null : ($n === 'THA' || $n === 'ไทย');
        };
        $sexOf = fn ($id) => $natOf[$id][1] ?? 0;
        // นับคน: ไทย / ต่างชาติ / รวม (สัญชาติว่าง นับในรวมอย่างเดียว)
        $cnt = function ($ids) use ($isThai) {
            $th = 0; $fx = 0; $n = 0;
            foreach ($ids as $id) { $n++; $t = $isThai($id); if ($t === true) { $th++; } elseif ($t === false) { $fx++; } }
            return [$th, $fx, $n];
        };
        // นับโดส: แต่ละรายการคือ patient_id 1 โดส
        $cntDoses = fn (array $pidList) => $cnt($pidList);
        $row = fn ($no, $label, $unit, $c, $sub = true) => ['no' => $no, 'label' => $label, 'unit' => $unit, 'th' => $c[0], 'fx' => $c[1], 'total' => $c[2], 'sub' => $sub];
        $na  = fn ($no, $label, $unit = 'ราย', $sub = true) => ['no' => $no, 'label' => $label, 'unit' => $unit, 'na' => true, 'sub' => $sub];

        $visitP = $pid($visits);
        $vacP   = $pid($shots)->merge(array_keys($takeHome))->unique()->values();
        $consP  = $visitP->diff($vacP)->values();
        $male   = fn ($ids) => collect($ids)->filter(fn ($id) => $sexOf($id) === 1);
        $female = fn ($ids) => collect($ids)->filter(fn ($id) => $sexOf($id) === 2);

        // ช่วงการเดินทางของคนที่มาปรึกษาอย่างเดียว — ว่าง = ก่อนเดินทาง (ส่วนใหญ่มาก่อนไป) · ระหว่างเดินทาง นับเป็นหลังเดินทาง
        // "ไม่มีการเดินทาง" (2569-11-05) นับรวมก่อนเดินทาง — แบบฟอร์ม ผอ. มีแค่ก่อน/หลัง ไม่งั้นข้อ 2.1–2.4 รวมไม่เท่าข้อ 2.0
        $phase = [];
        foreach ($visits as $v) {
            $ph = (string) ($v->tmc_travel_phase ?? '');
            if (! isset($phase[$v->patient_id]) || $ph === 'after' || $ph === 'during') { $phase[$v->patient_id] = in_array($ph, ['after', 'during'], true) ? 'after' : 'before'; }
        }
        $before = $consP->filter(fn ($id) => ($phase[$id] ?? 'before') === 'before');
        $after  = $consP->filter(fn ($id) => ($phase[$id] ?? 'before') === 'after');

        $out = [];
        $out[] = $row('1.0', 'ผู้มารับบริการทั้งหมด', 'ราย', $cnt($visitP), false);
        $out[] = $row('', '1.1 ชาย', 'ราย', $cnt($male($visitP)));
        $out[] = $row('', '1.2 หญิง', 'ราย', $cnt($female($visitP)));
        $out[] = $row('2.0', 'กิจกรรมมารับบริการให้คำปรึกษา', 'ราย', $cnt($consP), false);
        $out[] = $row('', '2.1 ก่อนเดินทาง (ชาย)', 'ราย', $cnt($male($before)));
        $out[] = $row('', '2.2 ก่อนเดินทาง (หญิง)', 'ราย', $cnt($female($before)));
        $out[] = $row('', '2.3 หลังเดินทาง (ชาย)', 'ราย', $cnt($male($after)));
        $out[] = $row('', '2.4 หลังเดินทาง (หญิง)', 'ราย', $cnt($female($after)));
        $out[] = $row('3.0', 'กิจกรรมมารับบริการฉีดวัคซีนไปต่างประเทศ', 'ราย', $cnt($vacP), false);
        $out[] = $row('', '3.1 ชาย', 'ราย', $cnt($male($vacP)));
        $out[] = $row('', '3.2 หญิง', 'ราย', $cnt($female($vacP)));

        // 4.0 โดสตามชนิด — ลำดับตามแบบฟอร์ม · ชนิดที่แบบฟอร์มไม่มีช่อง ต่อท้าย
        $byGroup = [];
        foreach ($shots as $s) { $byGroup[self::groupOf($s->name, $s->mname) ?: 'x:' . trim((string) $s->name)][] = $s->patient_id; }
        foreach ($takeHome as $id => $n) { for ($i = 0; $i < $n; $i++) { $byGroup['cholera'][] = $id; } }
        $allDoses = array_merge(...array_values($byGroup ?: [[]]));
        $form = ['yf' => 'ไข้เหลือง', 'menacwy' => 'ไข้กาฬหลังแอ่น group A,C,Y,W-135', 'menb' => 'ไข้กาฬหลังแอ่น group B', 'flu' => 'ไข้หวัดใหญ่',
            'cholera' => 'อหิวาตกโรค', 'rabies' => 'RABIES VACCINE', 'dt' => 'dT', 'tdap' => 'Tdap', 'hpv4' => 'HPV-4', 'hpv9' => 'HPV-9',
            'twinrix' => 'ไวรัสตับอักเสบ A และ B (Hepatitis A&B)', 'hepa' => 'ไวรัสตับอักเสบเอ (HAV)', 'hepb' => 'ไวรัสตับอักเสบบี (HBV)', 'je' => 'JE',
            'dengue' => 'DENGUE VACCINE', 'mmr' => 'MMR', 'rsv' => 'RSV', 'zoster' => 'งูสวัด (Recombinant Zoster)', 'pcv' => 'ปอดอักเสบ 20 สายพันธุ์ (PCV20)'];
        $out[] = $row('4.0', 'ผู้มารับบริการวัคซีน จำแนกตามประเภทวัคซีน', 'โด๊ส', $cntDoses($allDoses), false);
        $i = 1;
        foreach ($form as $k => $label) { $out[] = $row('', '4.' . $i++ . ' ' . $label, 'โด๊ส', $cntDoses($byGroup[$k] ?? [])); }
        foreach ($byGroup as $k => $list) {
            if (isset($form[$k])) { continue; }
            $label = str_starts_with($k, 'x:') ? substr($k, 2) : (self::GROUPS[$k][0] ?? $k);
            $out[] = $row('', '4.' . $i++ . ' ' . $label . ' (ไม่มีช่องในแบบฟอร์ม)', 'โด๊ส', $cntDoses($list));
        }
        $out[] = $row('', '4.' . $i . ' ให้คำแนะนำปรึกษาก่อนและหลังให้วัคซีน', 'ราย', $cnt($vacP));

        $out[] = $na('5.0', 'การตรวจสุขภาพ', 'ราย', false);
        $out[] = $na('', '5.1 ก่อนเดินทาง');
        $out[] = $na('', '5.2 หลังเดินทาง');

        // 6.0 ส่งต่อ: วันเดียวกันไปเปิดงานคลินิกกามโรค (1) / วัณโรค (2) = ส่งต่อภายใน · ช่อง Refer ในหน้าตรวจ = โรงพยาบาลอื่น
        $sameDay = function (int $clinic) use ($visits) {
            $pairs = collect($visits)->map(fn ($v) => $v->patient_id . '|' . $v->date)->unique();
            if ($pairs->isEmpty()) { return collect(); }
            return DB::table('visits')->where('clinic', $clinic)->whereIn('patient_id', collect($visits)->pluck('patient_id')->unique()->all())
                ->whereIn('date', collect($visits)->pluck('date')->unique()->all())->get(['patient_id', 'date'])
                ->filter(fn ($r) => $pairs->contains($r->patient_id . '|' . $r->date))->pluck('patient_id')->unique();
        };
        $sti = $sameDay(1); $tb = $sameDay(2);
        $ref = collect($visits)->filter(fn ($v) => trim((string) ($v->refer_hospital ?? '')) !== '')->pluck('patient_id')->unique();
        $out[] = $row('6.0', 'การส่งต่อไปรับบริการ', 'ราย', $cnt($sti->merge($tb)->merge($ref)->unique()), false);
        $out[] = $row('', 'กามโรค', 'ราย', $cnt($sti));
        $out[] = $row('', 'วัณโรค', 'ราย', $cnt($tb));
        $out[] = $na('', 'มาลาเรีย');
        $out[] = $row('', 'โรงพยาบาลอื่น', 'ราย', $cnt($ref));
        $out[] = $na('7.0', 'ผู้มาศึกษาดูงาน', 'ราย', false);
        $out[] = $na('', 'ชาวไทย');
        $out[] = $na('', 'ชาวต่างชาติ');
        $out[] = $na('8.0', 'ออกหนังสือรับรองการสร้างเสริมภูมิคุ้มกันโควิด', 'ราย', false);

        return ['rows' => $out, 'unknownNat' => collect(array_keys($natOf))->filter(fn ($id) => $isThai($id) === null)->count()];
    }

    /** รวมทั้งปีงบประมาณ (ต.ค.–ก.ย.) ของแบบรายงานส่ง ผอ. — คอลัมน์รายเดือน + รวมทั้งปี (แบบชีท "รวมรายเดือน" + "Total") */
    public function year(Request $request)
    {
        $fy = (int) $request->get('fy', 0);
        $nowFy = now()->year + 543 + (now()->month >= 10 ? 1 : 0);
        if ($fy < 2560 || $fy > $nowFy + 1) { $fy = $nowFy; }
        $months = [];
        for ($i = 0; $i < 12; $i++) {
            $start = Carbon::create($fy - 543 - 1, 10, 1)->addMonthsNoOverflow($i)->startOfMonth();
            if ($start->isFuture()) { break; }
            $end = $start->copy()->endOfMonth();
            [$beFrom, $beTo, $visits, $shots, $takeHome] = $this->monthData($start, $end);
            $months[] = ['start' => $start, 'rows' => $this->director($visits, $shots, $takeHome, $beFrom, $beTo)['rows']];
        }
        // รวมทั้งปี: ไทย/ต่างชาติ/รวม ของแต่ละแถว = ผลรวมรายเดือน (คนมาหลายเดือนนับทุกเดือน เหมือนชีท Total)
        // จับคู่แถวด้วยชื่อแถว — แถววัคซีน (4.x) ตัดเลขข้อออก เพราะชนิดที่ไม่มีช่องในแบบฟอร์มมีไม่เท่ากันทุกเดือน เลขข้อเลยเลื่อน
        $total = [];
        foreach ($months as $m) {
            foreach ($m['rows'] as $r) {
                $k = str_starts_with($r['label'], '4.') ? 'vac|' . preg_replace('/^4\.\d+\s+/u', '', $r['label']) : $r['no'] . '|' . $r['label'];
                if (! isset($total[$k])) { $total[$k] = ['th' => 0, 'fx' => 0, 'total' => 0] + $r; $total[$k]['th'] = 0; $total[$k]['fx'] = 0; $total[$k]['total'] = 0; }
                if (! empty($r['na'])) { continue; }
                $total[$k]['th'] += $r['th']; $total[$k]['fx'] += $r['fx']; $total[$k]['total'] += $r['total'];
            }
        }
        $total = array_values($total);
        return view('report.tmc_year', compact('fy', 'months', 'total'));
    }

    /**
     * สถิติสมุดเหลืองของเดือน — อ่านจากประวัติการแก้ไขข้อมูลคนไข้ (activity_logs) เพราะเลขเล่มเก็บที่ตัวคนไข้
     * ไม่ได้ผูกกับ visit จึงต้องดูว่า "เปลี่ยนเมื่อไหร่ จากอะไรเป็นอะไร"
     *   เลขว่าง -> มีเลข                       = ออกเล่มแรก
     *   เลขเดิม -> เลขใหม่ พร้อมประวัติเล่มเก่า  = ปุ่ม "ออกเล่มใหม่" (เหตุผล ชำรุด/หาย = ทดแทน, เสีย = สมุดเสีย)
     *   เลขเดิม -> เลขใหม่ ไม่มีประวัติ          = แก้เลขที่พิมพ์ผิด ไม่นับ
     * ใช้เล่มเดิม = คนที่ได้ฉีดเดือนนี้ และมีเลขเล่มอยู่แล้วก่อนต้นเดือน (สูตรชีทเดิมก็นับแบบนี้)
     */
    private function bookStats(Carbon $start, Carbon $end, $shots): array
    {
        // นับเฉพาะเข็มที่ฉีดที่คลินิก — อหิวาต์ที่รับไปทานเองลงวันที่ทาน (มักเป็นเดือนถัดไป) คนไข้ไม่ได้มาพร้อมสมุด
        // เคยทำให้คนที่มาเดือน ต.ค. ไปโผล่ "ใช้เล่มเดิม" เดือน พ.ย. (คลินิกเจอ 2569-11-04)
        $shots = $shots->filter(fn ($s) => (int) $s->source === VisitVaccine::HERE)->values();
        $vaccinatedIds = $shots->pluck('patient_id')->unique()->values()->all();
        $events = [];        // รายการไว้ตรวจทานในหน้า
        $out = ['old' => 0, 'replace' => 0, 'spoiled' => 0, 'issued' => 0];

        // ---- แบบใหม่ (2569-11-04): พยาบาลเลือก "สมุดเหลืองครั้งนี้" ที่ visit — นับตรงจากที่เลือก ----
        // คนที่มีรายการแบบใหม่ในเดือนนี้ ไม่เอาไปคิดแบบเดาจากประวัติการแก้เลขซ้ำ (การบันทึกแบบใหม่ก็แก้เลขที่ตัวคนไข้ด้วย)
        $covered = [];
        if (\App\Models\Visit::bookReady()) {
            $rows = DB::table('visits as v')->join('patients as p', 'p.id', '=', 'v.patient_id')
                ->where('v.clinic', 3)->whereNotNull('v.tmc_book_action')
                ->whereBetween('v.date', [($start->year + 543) . $start->format('-m-d'), ($end->year + 543) . $end->format('-m-d')])
                ->orderBy('v.date')->orderBy('v.id')
                ->get(array_merge(['v.patient_id', 'v.date', 'v.updated_at', 'v.tmc_book_action as act', 'v.tmc_book_no as no', 'v.tmc_book_old_no as old_no',
                       'v.tmc_book_old_src as src', 'v.tmc_book_old_place as place', 'p.code', 'p.name', 'p.surname', 'p.yellow_book_history as hist'],
                       \App\Models\Visit::bookSpoiledReady() ? ['v.tmc_book_spoiled_nos as spoiled_nos'] : []));
            $oldBy = [];
            foreach ($rows as $r) {
                $covered[$r->patient_id] = true;
                // หน้ารายงานแสดงวันที่จาก ค.ศ. — แปลงวันที่ visit (พ.ศ.) กลับ + เวลาที่กดบันทึก
                $ce = preg_match('/^(\d{4})(-\d{2}-\d{2})/', (string) $r->date, $m) ? (((int) $m[1] > 2400 ? (int) $m[1] - 543 : (int) $m[1]) . $m[2]) : '';
                if ($r->act === 'old') {
                    // ใช้เล่มเดิม นับคนละครั้ง (มาหลายรอบในเดือนก็ 1) — โชว์แถวเดียวเหมือนกัน
                    if (!isset($oldBy[$r->patient_id])) {
                        $events[] = ['date' => $ce . ' ' . substr((string) $r->updated_at, 11, 8), 'label' => trim($r->code . ' ' . $r->name . ' ' . $r->surname),
                            'kind' => 'old', 'old' => (string) ($r->no ?: $r->old_no) . ($r->src === 'other' ? ' (เล่มจากที่อื่น)' : ''), 'new' => '', 'manual' => true];
                    }
                    $oldBy[$r->patient_id] = true;
                    continue;
                }
                if (! in_array($r->act, ['new', 'replace', 'lost', 'spoiled'], true)) { continue; }
                $out['issued']++;
                if ($r->act !== 'new') { $out[$r->act === 'spoiled' ? 'spoiled' : 'replace']++; }
                $kind = $r->act === 'new' ? 'first' : $r->act;
                $book = (string) $r->old_no;
                if ($r->act === 'replace') {
                    // ก่อนแยกปุ่ม "สมุดหาย" (2569-11-04) ชำรุด/หายอยู่ปุ่มเดียว เหตุผลอยู่ในประวัติเล่มเก่า "เล่มเก่า X — ชำรุด (วันที่)"
                    [$why, $histNo] = self::replaceReason($r->hist, $book);
                    $kind = $why;
                    if ($book === '') { $book = $histNo; }
                }
                $events[] = ['date' => $ce . ' ' . substr((string) $r->updated_at, 11, 8), 'label' => trim($r->code . ' ' . $r->name . ' ' . $r->surname),
                    'code' => (string) $r->code, 'kind' => $kind, 'book' => $book, 'src' => $r->src,
                    'old' => ($book === '' && $kind === 'lost' ? 'หาเลขเดิมไม่ได้' : $book) . ($r->src === 'other' ? ' (เล่มจากที่อื่น)' : ''),
                    'new' => (string) $r->no, 'manual' => true];
                // เล่มเปล่าที่เขียนเสียก่อนได้เล่มที่ให้คนไข้ (2569-11-06) — แต่ละเล่ม = สมุดเสีย 1 เล่ม เข้ารายการส่งทำลาย
                foreach (array_filter(explode(',', (string) ($r->spoiled_nos ?? ''))) as $sx) {
                    $out['spoiled']++;
                    $events[] = ['date' => $ce . ' ' . substr((string) $r->updated_at, 11, 8), 'label' => trim($r->code . ' ' . $r->name . ' ' . $r->surname),
                        'code' => (string) $r->code, 'kind' => 'spoiled', 'book' => $sx, 'src' => 'ours',
                        'old' => $sx, 'new' => (string) $r->no, 'manual' => true];
                }
            }
            $out['old'] += count($oldBy);
        }

        // ---- แบบเดิม (เดือนก่อนมีกล่องใหม่ / visit ที่ยังไม่ได้เลือก): เดาจากประวัติการแก้เลขเล่มของคนไข้ ----
        $anyNew = \App\Models\Visit::bookReady()
            ? DB::table('visits')->where('clinic', 3)->whereNotNull('tmc_book_action')->pluck('patient_id')->flip()->all()
            : [];
        $logs = DB::table('activity_logs')
            ->where('subject_type', \App\Models\Patient::class)->where('action', 'updated')
            ->where('changes', 'like', '%yellow_book_no%')
            ->where('created_at', '>=', $start->toDateTimeString())
            ->orderBy('id')->get(['subject_id', 'subject_label', 'changes', 'created_at'])
            // คนที่ลงสมุดด้วยกล่องใหม่แล้ว (visit เดือนไหนก็ตาม) ไม่เอาประวัติการแก้เลขมานับ
            // เจอจริง 2569-11-04: ลงย้อนหลังของ ต.ค. ด้วยกล่องเก่าก่อน แล้วลงซ้ำด้วยกล่องใหม่ → ไปโผล่เป็นยอด พ.ย. ตามเวลาที่กด
            ->reject(fn ($l) => isset($covered[$l->subject_id]) || isset($anyNew[$l->subject_id]));

        $firstBy = [];       // คนที่ออกเล่มแรกในเดือนนี้ (นับครั้งเดียว — autosave อาจบันทึกหลายรอบตอนพิมพ์)
        $startNo = [];       // เลขเล่ม ณ ต้นเดือน = ค่า old ของการแก้ครั้งแรกนับจากต้นเดือน
        foreach ($logs as $l) {
            $c = json_decode((string) $l->changes, true) ?: [];
            $yb = $c['yellow_book_no'] ?? null;
            if (!$yb) { continue; }
            $old = trim((string) ($yb['old'] ?? ''));
            $new = trim((string) ($yb['new'] ?? ''));
            $startNo[$l->subject_id] ??= $old;
            if ($l->created_at > $end->copy()->endOfDay()->toDateTimeString()) { continue; }

            $kind = null;
            if ($old === '' && $new !== '') {
                if (isset($firstBy[$l->subject_id])) { continue; }
                $firstBy[$l->subject_id] = true;
                $kind = 'first';
            } elseif ($old !== '' && $new !== '' && $old !== $new && isset($c['yellow_book_history'])) {
                // กล่องเก่าเขียน "เล่มเก่า X — ชำรุด|หาย|เสีย (วันที่)" ต่อท้ายประวัติ
                $hist = (string) ($c['yellow_book_history']['new'] ?? '');
                $last = trim((string) last(explode('|', $hist)));
                $kind = preg_match('/—\s*เสีย/u', $last) ? 'spoiled'
                    : (preg_match('/—\s*ชำรุด/u', $last) ? 'damaged' : (preg_match('/—\s*หาย/u', $last) ? 'lost' : 'replace'));
            }
            if (!$kind) { continue; }
            $out['issued']++;
            if ($kind !== 'first') { $out[$kind === 'spoiled' ? 'spoiled' : 'replace']++; }
            $events[] = ['date' => $l->created_at, 'label' => $l->subject_label, 'code' => strtok((string) $l->subject_label, ' '),
                'kind' => $kind, 'book' => $old, 'src' => null, 'old' => $old, 'new' => $new];
        }

        $current = \App\Models\Patient::whereIn('id', $vaccinatedIds)->pluck('yellow_book_no', 'id');
        $firstShot = $shots->sortBy('vaccinated_date')->unique('patient_id')->keyBy('patient_id');
        foreach ($vaccinatedIds as $id) {
            if (isset($covered[$id])) { continue; }
            $no = array_key_exists($id, $startNo) ? $startNo[$id] : trim((string) ($current[$id] ?? ''));
            if ($no === '') { continue; }
            $out['old']++;
            // คนที่ไม่ได้เลือกในกล่องสมุดเหลือง — ระบบคิดเอาว่าใช้เล่มเดิม (ได้ฉีดเดือนนี้ + มีเลขเล่มก่อนต้นเดือน) วันที่ = วันที่ฉีดเข็มแรกของเดือน
            $s = $firstShot[$id] ?? null;
            $events[] = ['date' => $s ? (string) $s->vaccinated_date : '', 'label' => $s ? trim($s->code . ' ' . $s->pname . ' ' . $s->surname) : (string) $id,
                'kind' => 'old', 'old' => $no, 'new' => '', 'inferred' => true];
        }
        usort($events, fn ($a, $b) => strcmp((string) $a['date'], (string) $b['date']));
        $out['events'] = $events;
        return $out;
    }

    /**
     * เหตุผลที่ออกเล่มทดแทน จากประวัติเล่มเก่าของคนไข้ → ['damaged'|'lost'|'replace', เลขเล่มเก่า]
     * มีเลขเล่มเก่า = หารายการของเล่มนั้น · ไม่มีเลข (ไม่บังคับกรอก) = รายการชำรุด/หายล่าสุด
     * หาไม่เจอ (ลบประวัติไปแล้ว) = 'replace' ไม่ทราบสาเหตุ ไม่เดาว่าเป็นชำรุด เพราะจะไปโผล่ในรายการส่งทำลาย
     */
    private static function replaceReason(?string $hist, string $oldNo): array
    {
        $hit = null;
        foreach (TmcYellowBookController::histList($hist) as $e) {
            if (!preg_match('/^เล่มเก่า (\S+).*?—\s*(ชำรุด|หาย)/u', $e, $m)) { continue; }
            if ($oldNo === '' || $m[1] === $oldNo) { $hit = $m; }
        }
        if (!$hit) { return ['replace', '']; }
        return [$hit[2] === 'หาย' ? 'lost' : 'damaged', $hit[1] === 'ไม่ทราบเลข' ? '' : $hit[1]];
    }

    /** นับคนไม่ซ้ำ แยกไทย/ต่างชาติ × ชาย/หญิง — สัญชาติว่างนับเป็น "ไม่ระบุ" ไม่เดาว่าเป็นคนไทย */
    private function people($rows): array
    {
        $out = ['total' => 0, 'th' => [0, 0, 0], 'fx' => [0, 0, 0], 'na' => 0];
        foreach ($rows->unique('patient_id') as $r) {
            $out['total']++;
            $nat = strtoupper(trim((string) $r->nationality));
            $sx = (int) $r->sex === 1 ? 0 : ((int) $r->sex === 2 ? 1 : 2);
            if ($nat === '') { $out['na']++; continue; }
            $out[$nat === 'THA' || $nat === 'ไทย' ? 'th' : 'fx'][$sx]++;
        }
        return $out;
    }
}
