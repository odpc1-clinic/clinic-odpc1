<?php

namespace App\Http\Controllers;

use App\Models\ContactPerson;
use App\Models\ContactSti;
use App\Models\Patient;
use App\Models\Visit;
use App\Utillity;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * สมุดทะเบียนติดตามผู้ป่วย 5 โรคหลัก + คู่สัมผัส (ContactSTI)
 * - ดึงข้อมูลคนไข้ STI รายเดือน (เฉพาะที่วินิจฉัย 5 โรคหลัก)
 * - ช่องที่กรอกเอง autosave: บางช่องลง visits (การติดตามคู่สัมผัส/ผล HIV), บางช่องลงตาราง contact_sti
 */
class ContactStiController extends Controller
{
    /** whitelist ช่องที่ autosave ได้ แยกตามตารางปลายทาง */
    private const SAVE_FIELDS = [
        'visit'          => ['hiv_sti_test_resule', 'hiv_sti_test_date'],   // ผล HIV ของ visit
        'contact_sti'    => ['patient_type', 'fu_status', 'note', 'hiv_service'], // ช่องที่ไม่มีในระบบเดิม
        'contact_person' => ['date', 'type', 'trush_tracks', 'cpsex'],     // ดึง/แก้ตารางคู่สัมผัสจริง
    ];

    /** ชื่อเดือนไทยย่อ */
    private const TH_MONTHS = [1=>'ม.ค.',2=>'ก.พ.',3=>'มี.ค.',4=>'เม.ย.',5=>'พ.ค.',6=>'มิ.ย.',7=>'ก.ค.',8=>'ส.ค.',9=>'ก.ย.',10=>'ต.ค.',11=>'พ.ย.',12=>'ธ.ค.'];

    public function index(Request $request)
    {
        [$from, $to] = $this->parseRange($request);
        $mode = $request->get('mode') === 'fu' ? 'fu' : 'visit';

        return view('contact_sti.index', [
            'rows'   => $this->collectRows($from, $to, $mode),
            'from'   => $from,
            'to'     => $to,
            'fromTh' => Utillity::db2thDate($from),
            'toTh'   => Utillity::db2thDate($to),
            'mode'   => $mode,
        ]);
    }

    /** ส่งออก Excel ตามช่วงเดือนที่เลือก */
    public function exportExcel(Request $request)
    {
        [$from, $to] = $this->parseRange($request);
        $mode = $request->get('mode') === 'fu' ? 'fu' : 'visit';
        $rows = $this->collectRows($from, $to, $mode);

        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('ContactSTI');

        $headers = ['วันที่', 'ประเภท', 'เพศ', 'HN', 'ชื่อ-สกุล', 'โรค', 'วันนัด F/U', 'สถานะ F/U',
                    'วันมีเพศสัมพันธ์ใน3ด', 'เพศคู่สัมผัส', 'คู่สัมผัส', 'การติดตามคู่สัมผัส', 'การตรวจ HIV', 'หมายเหตุ'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:N1')->getFont()->setBold(true);

        $r = 2;
        foreach ($rows as $row) {
            $vf = $row['is_visit_first'];
            $fu = $vf ? trim(($row['appointment'] ?? '') . ($row['came'] ? ' (มาแล้ว ' . $row['came_date'] . ')' : '')) : '';
            $sheet->fromArray([
                $vf ? $row['date'] : '',
                $vf ? $row['type'] : '',
                $vf ? $row['sex'] : '',
                $vf ? $row['hn'] : '',
                $vf ? $row['name'] : '',
                $vf ? $row['disease'] : '',
                $fu,
                $vf ? $row['fu_status'] : '',
                $row['sex_date'],
                ContactPerson::$cpsexOption[(int) $row['partner_sex']] ?? '',
                ContactPerson::$typeOption[(int) $row['partner_type']] ?? '',
                ContactPerson::$touchTracingOption[(int) $row['tracing']] ?? '',
                $vf ? ($row['hiv_service'] ?? '') : '',
                $vf ? $row['note'] : '',
            ], null, 'A' . $r);
            $r++;
        }
        foreach (range('A', 'N') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $name = 'ContactSTI_' . $from . ($from !== $to ? '_ถึง_' . $to : '') . '.xlsx';
        $writer = new Xlsx($ss);
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * อ่านช่วงวันที่จาก request -> [from(BE yyyy-mm-dd), to(BE yyyy-mm-dd)]
     * รับได้ทั้งวัน-วัน (from/to เป็น dd/mm/yyyy หรือ yyyy-mm-dd) ; ค่าเริ่มต้น = เดือนปัจจุบัน
     */
    protected function parseRange(Request $request): array
    {
        $now = Carbon::now();
        $beY = $now->year + 543;
        $defFrom = sprintf('%04d-%02d-01', $beY, $now->month);
        $defTo   = sprintf('%04d-%02d-%02d', $beY, $now->month, $now->daysInMonth);

        $from = $this->toBeDbDate($request->get('from'), $defFrom);
        $to   = $this->toBeDbDate($request->get('to'), $defTo);
        if ($from > $to) { [$from, $to] = [$to, $from]; }   // สลับถ้ากลับ

        return [$from, $to];
    }

    /** รับ dd/mm/yyyy (จาก datepicker พ.ศ.) หรือ yyyy-mm-dd -> คืน พ.ศ. yyyy-mm-dd */
    protected function toBeDbDate(?string $val, string $default): string
    {
        $val = trim((string) $val);
        if ($val === '') return $default;
        if (preg_match('#^\d{4}-\d{2}-\d{2}$#', $val)) return $val;
        if (preg_match('#^\d{1,2}/\d{1,2}/\d{4}$#', $val)) {
            try { return Utillity::th2dbDate($val) ?: $default; } catch (\Throwable $e) { return $default; }
        }
        return $default;
    }

    /**
     * ประกอบแถวข้อมูล (ใช้ร่วมทั้งหน้าเว็บและ export)
     * @param string $mode 'visit'=กรอง/เรียงตามวันที่มา · 'fu'=ตามวันนัด F/U (visits.appointment)
     */
    protected function collectRows(string $from, string $to, string $mode = 'visit'): array
    {
        $dateCol = $mode === 'fu' ? 'v.appointment' : 'v.date';

        $visits = DB::table('visits as v')
            ->join('patients as p', 'p.id', '=', 'v.patient_id')
            ->where('v.clinic', 1)
            ->whereBetween($dateCol, [$from, $to]) // appointment เป็น DATE — NULL/ไม่มีนัด ถูกคัดออกเอง
            ->whereExists(function ($q) {   // มีวินิจฉัย 5 โรคหลัก ที่เป็น "ผู้ป่วยใหม่/เกิดโรคซ้ำ"
                $q->select(DB::raw(1))->from('diagnoses as d')
                  ->whereColumn('d.visit_id', 'v.id')
                  ->whereIn('d.disease', ContactSti::diseaseIds())
                  ->whereIn('d.disease_state', ContactSti::CASE_STATES);
            })
            // ไม่กรองว่าต้องมีคู่สัมผัส — เคสใหม่/ซ้ำต้องขึ้นเสมอ (ยังไม่มีคู่สัมผัสก็เติมทีหลังได้)
            ->orderBy($dateCol)->orderBy('v.id')   // เรียงตามคอลัมน์ที่กรอง
            ->select('v.id as visit_id', 'v.date', 'v.appointment', 'v.touch_tracing',
                     'v.hiv_sti_test_resule', 'v.hiv_sti_test_date', 'v.last_hiv_syphilis',
                     'p.id as patient_id', 'p.code', 'p.name', 'p.surname', 'p.sex', 'p.status')
            ->get();

        $visitIds = $visits->pluck('visit_id')->all();

        $diseaseByVisit = [];
        if ($visitIds) {
            foreach (DB::table('diagnoses')->whereIn('visit_id', $visitIds)
                        ->whereIn('disease', ContactSti::diseaseIds())
                        ->whereIn('disease_state', ContactSti::CASE_STATES)
                        ->select('visit_id', 'disease')->get() as $d) {
                $diseaseByVisit[$d->visit_id][ContactSti::$diseaseToName[(int) $d->disease] ?? ''] = true;
            }
        }

        $contactSti = $visitIds
            ? ContactSti::whereIn('visit_id', $visitIds)->get()->keyBy('visit_id')
            : collect();

        $partnersByVisit = [];
        if ($visitIds) {
            foreach (ContactPerson::whereIn('visit_id', $visitIds)->orderBy('id')->get() as $cp) {
                $partnersByVisit[$cp->visit_id][] = $cp;
            }
        }

        // "มาตามนัด" = คนไข้มี visit ถัดไป (date > visit นี้) — ใช้ logic เดียวกับหน้านัดหมาย
        $patientIds = $visits->pluck('patient_id')->unique()->all();
        $datesByPatient = [];
        if ($patientIds) {
            foreach (DB::table('visits')->where('clinic', 1)->whereIn('patient_id', $patientIds)
                        ->whereNotNull('date')->orderBy('date')
                        ->get(['patient_id', 'date']) as $vd) {
                $datesByPatient[$vd->patient_id][] = $vd->date;
            }
        }

        // visit ที่มีสั่งตรวจ Anti-HIV (method 6) ในแล็บเรา -> auto SDR
        $hivLabVisits = [];
        if ($visitIds) {
            $hivLabVisits = DB::table('lab_subs')
                ->join('labs', 'labs.id', '=', 'lab_subs.lab_id')
                ->whereIn('labs.visit_id', $visitIds)
                ->where('lab_subs.method', 6)              // 6 = # Anti-HIV
                ->pluck('labs.visit_id')->flip()->toArray(); // visit_id => index
        }

        $rows = [];
        foreach ($visits as $v) {
            $c = $contactSti->get($v->visit_id);
            $partners = $partnersByVisit[$v->visit_id] ?? [];
            $count = max(1, count($partners));

            // หา visit ถัดไปของคนไข้ (มาแล้ว)
            $cameDate = null;
            foreach ($datesByPatient[$v->patient_id] ?? [] as $dt) {
                if ($dt > $v->date) { $cameDate = $dt; break; }   // เรียง asc แล้ว -> ตัวแรกที่มากกว่า = ครั้งถัดไป
            }

            for ($i = 0; $i < $count; $i++) {
                $p = $partners[$i] ?? null;
                $rows[] = [
                    'is_visit_first' => ($i === 0),
                    'visit_id'   => $v->visit_id,
                    'patient_id' => $v->patient_id,
                    'date'       => $this->thDate($v->date),
                    'type'       => $c->patient_type ?? (ContactSti::$statusToType[(int) $v->status] ?? ''),
                    'sex'        => Patient::$sexOption[(int) $v->sex] ?? '',
                    'hn'         => $v->code,
                    'name'       => trim($v->name . ' ' . ($v->surname === '-' ? '' : $v->surname)),
                    'disease'    => implode(', ', array_keys($diseaseByVisit[$v->visit_id] ?? [])),
                    'appointment'=> $this->thDate($v->appointment),
                    'came'       => (bool) $cameDate,
                    'came_date'  => $cameDate ? $this->thDate($cameDate) : '',
                    'fu_status'  => $c->fu_status ?? '',
                    // ไม่โชว์ผลตรวจ (รักษาความลับ) — โชว์แค่ "สถานะบริการ"
                    'hiv_service'=> $this->autoHivService($v, $c, isset($hivLabVisits[$v->visit_id])),
                    'note'       => $c->note ?? '',
                    'contact_id'   => $p->id ?? null,
                    'partner_name' => $p->name_surname ?? '',
                    'partner_sex'  => $p->cpsex ?? '',        // เพศคู่สัมผัส (1ช/2ญ)
                    'sex_date'     => $p->date ?? '',
                    'partner_type' => $p->type ?? '',
                    'tracing'      => $p->trush_tracks ?? '',
                ];
            }
        }

        return $rows;
    }

    /**
     * เติมคอลัมน์ "การตรวจ HIV" อัตโนมัติ (ตามที่พยาบาลกำหนด 2026-07-27)
     *
     * ลำดับความสำคัญ — ค่าที่พยาบาลเลือกเองชนะเสมอ ระบบเดาให้เฉพาะช่องที่ยังว่าง:
     *   1. ค่าที่บันทึกไว้แล้ว
     *   2. on ARV / on Prep  (สถานะคนไข้ สำคัญกว่าว่าวันนี้ตรวจอะไร — พยาบาลยืนยัน)
     *   3. SDR               (วันนี้มีสั่ง Anti-HIV ที่แล็บเรา)
     *   4. ตรวจแล้ว          (มีผลเดิม + ตรวจมาไม่เกิน 3 เดือน)
     *   5. เว้นว่าง          (ที่เหลือทั้งหมด รวมถึงกรณีอ่านวันที่ไม่ออก)
     *
     * ข้อ 5 เคยเติม "Advice" ให้อัตโนมัติ — พี่อิ๋วขอเอาออก 21 ส.ค. 2569
     * เพราะ Advice เป็นสิ่งที่ต้องทำจริงกับคนไข้ ไม่ใช่ค่าที่เดาแทนกันได้
     * เติมให้เองแล้วแยกไม่ออกว่า "แนะนำไปแล้ว" หรือ "ระบบเดาให้" ต้องให้คนกรอกเอง
     * ข้ออื่นเดาต่อเหมือนเดิม เพราะอ้างอิงจากข้อมูลที่มีอยู่จริงในระบบ
     */
    private function autoHivService($v, $c, bool $hasHivLabToday): string
    {
        $stored = trim((string) ($c->hiv_service ?? ''));
        if ($stored !== '') { return $stored; }

        $status = ContactSti::detectHivStatus($v->last_hiv_syphilis ?? null, $v->hiv_sti_test_date ?? null);
        if ($status !== null) { return $status; }

        if ($hasHivLabToday) { return 'SDR'; }

        // ผลเดิมนับได้เฉพาะที่ยังไม่เกิน 3 เดือน; อ่านวันที่ไม่ออก = ไม่นับ (ถือเป็น Advice)
        if ((int) ($v->hiv_sti_test_resule ?? 0) > 0) {
            $age = ContactSti::testAgeMonths($v->hiv_sti_test_date ?? null, $v->date ?? null);
            if ($age !== null && $age <= 3) { return 'ตรวจแล้ว'; }
        }

        return '';   // เว้นว่างไว้ให้เจ้าหน้าที่เลือกเอง (ดูเหตุผลใน docblock ข้างบน)
    }

    /** autosave ทีละช่อง (target: visit | contact_sti | contact_person) */
    public function save(Request $request)
    {
        $target = (string) $request->input('target');
        $id     = (int) $request->input('id');
        $field  = (string) $request->input('field');
        $value  = $request->input('value');
        $value  = ($value === '' ? null : $value);

        if (!isset(self::SAVE_FIELDS[$target]) || !in_array($field, self::SAVE_FIELDS[$target], true)) {
            return response()->json(['ok' => false, 'msg' => 'ฟิลด์/ปลายทางไม่ถูกต้อง'], 422);
        }

        switch ($target) {
            case 'visit':
                if (!Visit::where('id', $id)->exists()) {
                    return response()->json(['ok' => false, 'msg' => 'ไม่พบ visit'], 404);
                }
                Visit::where('id', $id)->update([$field => $value]);
                break;

            case 'contact_sti': // id = visit_id
                if (!Visit::where('id', $id)->exists()) {
                    return response()->json(['ok' => false, 'msg' => 'ไม่พบ visit'], 404);
                }
                ContactSti::updateOrCreate(['visit_id' => $id], [$field => $value]);
                break;

            case 'contact_person':
                if (!ContactPerson::where('id', $id)->exists()) {
                    return response()->json(['ok' => false, 'msg' => 'ไม่พบคู่สัมผัส'], 404);
                }
                ContactPerson::where('id', $id)->update([$field => $value]);
                break;
        }

        return response()->json(['ok' => true]);
    }

    /** พ.ศ. yyyy-mm-dd -> dd/mm/yy (ว่างคืน '') */
    private function thDate(?string $be): string
    {
        $be = trim((string) $be);
        if (!preg_match('#^\d{4}-\d{2}-\d{2}$#', $be)) return $be; // เผื่อเป็นข้อความอื่น
        [$y, $m, $d] = explode('-', $be);
        return $d . '/' . $m . '/' . substr($y, 2, 2);
    }
}
