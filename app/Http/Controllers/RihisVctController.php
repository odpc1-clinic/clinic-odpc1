<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\RihisVct;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * ทะเบียน RIHIS-VCT — บริการปรึกษาและตรวจเอชไอวี
 *
 * รอบนี้พยาบาลเลือกลงเอง (ผู้ใช้ตัดสินใจ 2569-08-05) ยังไม่ดึงจาก visit อัตโนมัติ
 * มีปุ่มดึงข้อมูลคนไข้จาก HN ให้ช่วยกรอกส่วนที่ระบบรู้อยู่แล้ว
 *
 * ไฟล์ Excel ต้นฉบับที่ส่วนกลางแจก สูตรในชีท "สรุปรายงาน" พังเป็น #REF! ทั้งหมด
 * หน้านี้จึงคำนวณสรุปให้เองด้วย ไม่ต้องไปพึ่งสูตรในไฟล์
 */
class RihisVctController extends Controller
{
    /** ช่องที่ autosave ได้ — กันยิงฟิลด์นอกรายการเข้ามา */
    private const SAVE_FIELDS = [
        'service_date', 'name', 'surname', 'uic', 'hn', 'id_card', 'age', 'birth_date',
        'sex', 'marital', 'nationality', 'target_group', 'msm_sub', 'sw_mode',
        'occ_thai', 'occ_migrant', 'referral', 'past_service',
        'pre_test', 'hiv_test', 'post_test', 'result_date', 'blood_result',
        'referral_unit', 'referral_type', 'referrer', 'referrer_detail',
        'cd4_sent', 'cd4', 'repeat_appt', 'repeat_date', 'refer_other', 'refer_hospital',
        'test_method', 'followup', 'hospital1', 'note', 'form5',
    ];

    /**
     * ระยะห่างสูงสุดที่ยังถือว่า "มาตรวจ" กับ "มาฟังผล" เป็นการมาครั้งเดียวกัน
     * เกินกว่านี้ถือเป็นคนละรอบ ไม่จับคู่ให้ (กันไปจับ pre เมื่อ 2 ปีก่อนมารวมกับ post วันนี้)
     */
    private const PAIR_MAX_DAYS = 90;

    /** ปีงบประมาณ พ.ศ. -> ช่วงวันที่ พ.ศ. (1 ต.ค. ปีก่อน – 30 ก.ย.) — ใช้เป็นค่าตั้งต้นของตัวกรอง */
    private function fiscalRange(int $fy): array
    {
        return [($fy - 1) . '-10-01', $fy . '-09-30'];
    }

    private function currentFiscalYear(): int
    {
        $be = now()->year + 543;
        return (int) now()->month >= 10 ? $be + 1 : $be;
    }

    /**
     * ช่วงวันที่ที่กำลังดูอยู่ (พ.ศ. Y-m-d)
     * ไม่ส่งมา = ปีงบปัจจุบันทั้งปี ซึ่งเป็นช่วงที่ใช้บ่อยที่สุด
     */
    /** วันที่ พ.ศ. Y-m-d บวก/ลบจำนวนวัน (คิดผ่าน ค.ศ. ให้ปีอธิกสุรทินถูก) */
    private function shiftBe(string $be, int $days): string
    {
        [$y, $m, $d] = array_map('intval', explode('-', $be));
        $c = \Carbon\Carbon::create($y - 543, $m, $d)->addDays($days);
        return sprintf('%04d-%02d-%02d', $c->year + 543, $c->month, $c->day);
    }

    private function range(Request $request): array
    {
        [$dFrom, $dTo] = $this->fiscalRange($this->currentFiscalYear());

        return [
            $this->toBeDate($request->get('from')) ?: $dFrom,
            $this->toBeDate($request->get('to'))   ?: $dTo,
        ];
    }

    public function index(Request $request)
    {
        // กรองด้วยช่วงวันที่ตรงๆ ง่ายกว่าปีงบ+เดือน และครอบคลุมทุกแบบที่ต้องใช้
        // (ผู้ใช้เลือกเอง 2569-08-06 — เดิมผูกกับปีงบ ทำให้เคสคาบเกี่ยวปีงบยุ่งมาก)
        [$from, $to] = $this->range($request);

        $per = (int) $request->get('per', 50);
        if (!in_array($per, [25, 50, 100, 200], true)) { $per = 50; }

        // ค้นหาด้วย HN หรือชื่อ (พี่ผลนราขอ 2569-08-26)
        // ตั้งใจให้ "ค้นหาแล้วข้ามช่วงวันที่" เพราะคนค้นอยากเจอตัวคน ไม่ได้อยากเจอเฉพาะในเดือนนี้
        // ถ้าบังคับให้อยู่ในช่วงด้วย จะหาไม่เจอแล้วนึกว่าไม่มีในทะเบียน ทั้งที่มี
        $q = trim((string) $request->get('q', ''));

        $base = function () use ($from, $to, $q) {
            $qry = RihisVct::query();
            if ($q === '') {
                return $qry->whereBetween('service_date', [$from, $to]);
            }
            // แยกคำ เพื่อให้พิมพ์ "สมชาย ใจดี" แล้วเจอ ทั้งที่ชื่อกับนามสกุลอยู่คนละคอลัมน์
            foreach (preg_split('/\s+/u', $q) as $word) {
                if ($word === '') { continue; }
                $qry->where(function ($w) use ($word) {
                    $w->where('hn', 'LIKE', "%{$word}%")
                      ->orWhere('name', 'LIKE', "%{$word}%")
                      ->orWhere('surname', 'LIKE', "%{$word}%");
                });
            }
            return $qry;
        };

        // สรุปต้องนับ "ทั้งช่วงที่กรอง" ไม่ใช่แค่หน้าปัจจุบัน — ดึงเฉพาะคอลัมน์ที่ใช้นับ
        $forSummary = $base()->get(['sex', 'target_group', 'msm_sub', 'pre_test', 'hiv_test', 'post_test', 'blood_result', 'refer_other']);

        $rows = $base()->orderBy('service_date')->orderBy('id')
            ->paginate($per)->appends($request->only(['from', 'to', 'per', 'q']));

        return view('rihis_vct.index', [
            'rows'     => $rows,
            'from'     => $from,
            'to'       => $to,
            'fromTh'   => $this->thDate($from),
            'toTh'     => $this->thDate($to),
            'per'      => $per,
            'q'        => $q,
            'rangeCnt' => $forSummary->count(),
            'summary'  => $this->summarize($forSummary),
            'total'    => RihisVct::count(),
        ]);
    }

    /**
     * ดึงเคสจากระบบเข้าทะเบียน — กติกาที่พี่อิ๋วให้มา (2569-08-05)
     *
     *   Pre-test counseling  = วินิจฉัย "Pre-test"  + สถานะ "ตรวจเลือด HIV"        (13 + 21)
     *   HIV Testing          = มีผลตรวจ Anti-HIV ที่ห้องแล็บของเราเอง               (lab_subs.method = 6)
     *   Post-test counseling = วินิจฉัย "Post-test" + สถานะ "มาฟังผลเลือด HIV"      (14 + 8)
     *
     * 3 ช่องนี้ถือว่าระบบเป็นเจ้าของ ดึงทับทุกครั้ง — ถ้าตัวเลขไม่ตรง พี่อิ๋วจะกด HN
     * เข้าไปแก้ที่ visit ต้นทาง (บางเคสลืมลงวินิจฉัย) แล้วกดดึงใหม่
     * ช่องที่พยาบาลกรอกเอง (กลุ่มเป้าหมาย ผู้แนะนำ หมายเหตุ ฯลฯ) ไม่แตะ
     */
    public function sync(Request $request)
    {
        // ดึงเฉพาะช่วงวันที่ที่ดูอยู่บนหน้าจอ เผื่อหน้า-หลัง PAIR_MAX_DAYS ไว้จับคู่ตรวจ/ฟังผลที่คาบเกี่ยว (2569-11-09)
        // เดิมดึงทั้งฐานทุกครั้ง (~1,700 เคส · 7 พันกว่า query) พยาบาลรู้สึกว่าช้ากว่ารายงานอื่น และช้าขึ้นทุกเดือน
        // ช่วงที่ขยายแล้วยังครอบคู่ที่ห่างกันไม่เกิน 90 วันได้ครบ ผลในช่วงที่ดูเหมือนดึงทั้งฐาน
        // แถวนอกช่วงไม่แตะ (กดดึงอีกครั้งตอนเปลี่ยนไปดูช่วงนั้น)
        [$from, $to] = $this->range($request);
        $lo = $this->shiftBe($from, -self::PAIR_MAX_DAYS);
        $hi = $this->shiftBe($to, self::PAIR_MAX_DAYS);

        $pre = \DB::table('visits')->join('diagnoses', 'diagnoses.visit_id', '=', 'visits.id')
            ->whereBetween('visits.date', [$lo, $hi])
            ->where('diagnoses.disease', 13)->where('diagnoses.disease_state', 21)
            ->distinct()->pluck('visits.id')->all();

        $post = \DB::table('visits')->join('diagnoses', 'diagnoses.visit_id', '=', 'visits.id')
            ->whereBetween('visits.date', [$lo, $hi])
            ->where('diagnoses.disease', 14)->where('diagnoses.disease_state', 8)
            ->distinct()->pluck('visits.id')->all();

        $test = \DB::table('visits')->join('labs', 'labs.visit_id', '=', 'visits.id')
            ->join('lab_subs', 'lab_subs.lab_id', '=', 'labs.id')
            ->whereBetween('visits.date', [$lo, $hi])
            ->where('lab_subs.method', 6)
            ->distinct()->pluck('visits.id')->all();

        $ids = array_values(array_unique(array_merge($pre, $post, $test)));
        if (!$ids) {
            return redirect()->route('rihis-vct.index')
                ->with('flash_message', 'ไม่พบเคสที่เข้าเกณฑ์ในระบบ');
        }

        $preSet  = array_flip($pre);
        $postSet = array_flip($post);
        $testSet = array_flip($test);

        // จับคู่ pre กับ post ที่อยู่คนละ visit — 1 การมาตรวจ = pre + post คู่กัน = 1 ราย
        // ยุบเป็นแถวเดียวเสมอ แล้วนับที่ "วันที่มาตรวจ"
        // แต่ต้องห่างกันไม่เกิน PAIR_MAX_DAYS ไม่งั้นจะไปจับคู่กับการมาตรวจคนละรอบ
        [$mergedPost, $postDateOf, $postIdOf] = $this->pairPrePost($ids, $preSet, $postSet);

        $visits   = \App\Models\Visit::whereIn('id', $ids)->get()->keyBy('id');
        $patients = Patient::whereIn('id', $visits->pluck('patient_id')->filter()->unique())->get()->keyBy('id');
        $existing = RihisVct::whereIn('visit_id', $ids)->get()->keyBy('visit_id');

        // ผล Anti-HIV ของทุก visit ดึงทีเดียว (เดิมถามทีละ visit ทีละใบแล็บ) — กติกาเดียวกับ Visit::labResultByMethod(6)
        $labRes = [];
        $labs = \App\Models\Lab::whereIn('visit_id', $ids)->get()->filter(fn ($l) => $l->isResultVisible())->keyBy('id');
        if ($labs->isNotEmpty()) {
            foreach (\App\Models\LabSub::whereIn('lab_id', $labs->keys())->where('method', 6)->get(['lab_id', 'result']) as $sub) {
                $r = trim((string) $sub->result);
                if ($r !== '') { $labRes[$labs[$sub->lab_id]->visit_id][] = $r; }
            }
        }
        $consumedPost = [];   // ดึงทั้งหมดในรอบเดียว จึงไม่มีเคสค้างข้ามรอบให้ต้องกัน

        // ประวัติการตรวจ HIV: ต้องรู้ว่าคนไข้เคยตรวจ Anti-HIV ที่เราเมื่อไรบ้าง (ทุกปี ไม่ใช่แค่ปีงบนี้)
        // ดึงทีเดียวเป็นชุด แล้วค่อยไล่เทียบในหน่วยความจำ — ถ้า query ในลูปจะช้ามาก
        $history = [];
        foreach (\DB::table('visits')
                    ->join('labs', 'labs.visit_id', '=', 'visits.id')
                    ->join('lab_subs', 'lab_subs.lab_id', '=', 'labs.id')
                    ->where('lab_subs.method', 6)
                    ->distinct()
                    ->get(['visits.patient_id', 'visits.id as vid', 'visits.date']) as $h) {
            $history[$h->patient_id][] = ['date' => (string) $h->date, 'vid' => (int) $h->vid];
        }

        $added = 0; $updated = 0; $merged = 0; $skipped = 0; $b24 = 0;

        foreach ($ids as $vid) {
            $v = $visits[$vid] ?? null;
            if (!$v) { continue; }
            $p = $patients[$v->patient_id] ?? null;

            // visit ที่เป็น "ฟังผล" ของ pre ก่อนหน้า -> ไม่สร้างแถวของตัวเอง
            // (รวมถึงกรณีถูกยุบไปอยู่ปีงบก่อนหน้าแล้ว จะได้ไม่นับซ้ำตอน sync ปีงบนี้)
            if (isset($mergedPost[$vid]) || isset($consumedPost[$vid])) {
                $old = $existing[$vid] ?? null;
                if ($old) {
                    // เคยสร้างแถวไว้ตอน sync รอบก่อน — ลบทิ้งได้ถ้ายังไม่มีใครกรอกอะไรเพิ่ม
                    if ($this->hasManualData($old)) { $skipped++; }
                    else { $old->delete(); $merged++; }
                } else {
                    $merged++;
                }
                continue;
            }

            $row = $existing[$vid] ?? new RihisVct(['visit_id' => $vid]);
            $isNew = !$row->exists;

            // --- ช่องที่ระบบเป็นเจ้าของ: ทับทุกครั้ง ---
            $row->visit_id   = $vid;
            $row->patient_id = $v->patient_id;
            $row->pre_test   = isset($preSet[$vid])  ? 1 : 0;
            $row->hiv_test   = isset($testSet[$vid]) ? 1 : 0;
            // ฟังผลใน visit นี้ หรือกลับมาฟังผลอีก visit หนึ่ง (คนละวัน/คนละเดือน/คนละปีงบก็ได้)
            $row->post_test     = (isset($postSet[$vid]) || isset($postDateOf[$vid])) ? 1 : 0;
            $row->post_visit_id = $postIdOf[$vid] ?? null;

            // ผลเลือดจากแล็บเราเท่านั้น (ผ่านกติกา "ต้องอนุมัติแล้ว" ของห้องแล็บ)
            $res = implode(', ', array_unique($labRes[$vid] ?? []));
            $row->blood_result = $this->mapBloodResult($res);

            // วันฟังผล = วันที่มาฟังผลจริง (ถ้ากลับมาฟังอีกวัน ใช้วันนั้น)
            $row->result_date = $row->post_test ? ($postDateOf[$vid] ?? $v->date) : null;
            if (!$row->post_test) { $row->blood_result = null; }

            // --- ช่องที่ดึงจากทะเบียนคนไข้/visit (พี่อิ๋วสั่งเพิ่ม 2569-08-05) ---
            [$tg, $sub, $swm] = $this->mapTargetGroup($p);
            $row->target_group = $tg;
            $row->msm_sub      = $sub;
            $row->sw_mode      = $swm;

            // ส่งต่อมาจาก: เอาข้อความจากช่อง "ส่งต่อมาจาก" ของ visit ตรงๆ
            $sendFrom = trim((string) $v->send_from);
            $other    = trim((string) $v->other_from);
            if ($sendFrom !== '' && $other !== '' && $other !== $sendFrom) { $sendFrom .= ' (' . $other . ')'; }
            $row->referral_unit = $sendFrom !== '' ? mb_substr($sendFrom, 0, 150) : null;
            $row->referral      = $sendFrom !== '' ? 1 : 0;

            // SDR = ตรวจและฟังผลภายในวันเดียวกัน
            if ($row->post_test) {
                $row->test_method = ($row->result_date === $row->service_date) ? 1 : 2;
            }

            $row->marital     = $this->mapMarital($p);
            $row->nationality = $this->mapNationality($p->nationality ?? null);

            // ประวัติการตรวจ HIV: ใช้ข้อความที่พยาบาลพิมพ์ไว้ในหน้าตรวจตรงๆ (เช่น "1 ปี", "ไม่เคยตรวจ", "3 ด")
            // ถ้า visit นั้นไม่ได้กรอกไว้ ค่อยคำนวณจากประวัติการตรวจที่แล็บเราให้แทน
            $hivHist = trim((string) $v->hiv_sti_test_date);
            // ไม่มีปีงบให้อ้างอิงแล้ว -> ใช้ปีงบของวันที่มารับบริการนั้นเป็นตัวตั้ง
            $fyOfVisit = (int) substr($v->date, 5, 2) >= 10 ? (int) substr($v->date, 0, 4) + 1 : (int) substr($v->date, 0, 4);
            $row->past_service = $hivHist !== ''
                ? mb_substr($hivHist, 0, 60)
                : $this->pastServiceText($history[$v->patient_id] ?? [], (string) $v->date, $vid, ($fyOfVisit - 1) . '-10-01');

            // --- ช่องข้อมูลคนไข้: เติมเฉพาะที่ยังว่าง ไม่ทับของที่แก้ไว้แล้ว ---
            $fill = [
                'service_date' => $v->date,
                'hn'           => $p->code ?? null,
                'name'         => $p->name ?? null,
                'surname'      => $p->surname ?? null,
                'id_card'      => $p->id_card_number ?? null,
                'birth_date'   => $p->birth_date ?? null,
                'sex'          => in_array((int) ($p->sex ?? 0), [1, 2], true) ? (int) $p->sex : null,
            ];
            foreach ($fill as $f => $val) {
                if (($row->$f === null || $row->$f === '') && $val !== null && $val !== '') {
                    $row->$f = $val;
                }
            }
            if (!$row->age) { $row->age = $this->ageFromBe($row->birth_date, $row->service_date); }

            $row->save();
            $isNew ? $added++ : $updated++;

            // ผลเลือดบวก -> ลงโรคประจำตัว B24 ในทะเบียนคนไข้ให้เลย (พี่อิ๋วขอ 2569-08-06)
            // เติมต่อท้ายของเดิม ไม่เขียนทับ และไม่ใส่ซ้ำถ้ามีอยู่แล้ว
            if ((int) $row->blood_result === 2 && $p && $this->tagB24($p)) { $b24++; }
        }

        $msg = "ดึงข้อมูลจากระบบแล้ว — เพิ่มใหม่ {$added} เคส · อัปเดต {$updated} เคส";
        if ($merged)  { $msg .= " · รวม pre+post เป็นรายเดียว {$merged} เคส"; }
        if ($skipped) { $msg .= " · ไม่ยุบ {$skipped} เคส เพราะมีข้อมูลที่กรอกเองอยู่"; }
        if ($b24)     { $msg .= " · ลงโรคประจำตัว B24 ให้ {$b24} ราย"; }

        return redirect()->route('rihis-vct.index', $request->only(['from', 'to', 'per', 'q']))
            ->with('flash_message', $msg);
    }

    /**
     * จับคู่ visit ที่มาตรวจ (pre) กับ visit ที่กลับมาฟังผล (post) ของคนไข้คนเดียวกัน
     *
     * คืน [ $mergedPost, $postDateOf ]
     *   $mergedPost[visit ของ post] = true   -> visit นี้ถูกยุบรวมแล้ว ไม่ต้องมีแถวของตัวเอง
     *   $postDateOf[visit ของ pre]  = วันที่ -> เอาไปลงเป็น "วันฟังผล" ของแถว pre
     *
     * ยุบเฉพาะคู่ที่อยู่เดือนเดียวกัน · ข้ามเดือนปล่อยแยกแถว (ต้องรายงานคนละเดือน)
     * visit ที่มีทั้ง pre และ post ในตัวเองอยู่แล้ว ไม่เข้าเงื่อนไขนี้ (ส่วนใหญ่เป็นแบบนี้)
     */
    private function pairPrePost(array $ids, array $preSet, array $postSet): array
    {
        $rows = \DB::table('visits')->whereIn('id', $ids)
            ->orderBy('patient_id')->orderBy('date')->orderBy('id')
            ->get(['id', 'patient_id', 'date']);

        $byPatient = [];
        foreach ($rows as $r) { $byPatient[$r->patient_id][] = $r; }

        $mergedPost = [];
        $postDateOf = [];
        $postIdOf   = [];

        foreach ($byPatient as $list) {
            // เอาเฉพาะ visit ที่เป็น pre อย่างเดียว และ post อย่างเดียว
            $pres  = array_values(array_filter($list, fn ($x) => isset($preSet[$x->id])  && !isset($postSet[$x->id])));
            $posts = array_values(array_filter($list, fn ($x) => isset($postSet[$x->id]) && !isset($preSet[$x->id])));

            foreach ($pres as $pr) {
                foreach ($posts as $k => $po) {
                    if ($po->date < $pr->date) { continue; }              // ฟังผลต้องมาทีหลัง

                    // ห่างเกินกำหนด = คนละรอบการมาตรวจ ไม่ใช่คู่กัน ปล่อยแยกแถว
                    // (ถ้าไม่กันไว้ จะไปจับ pre เมื่อ 2 ปีก่อนกับ post วันนี้มารวมกัน)
                    if ($this->daysBetweenBe($pr->date, $po->date) > self::PAIR_MAX_DAYS) { break; }

                    // รวมทุกกรณี ข้ามเดือนหรือข้ามปีงบก็รวม — ให้ไปนับที่ "วันที่มาตรวจ" เสมอ
                    $mergedPost[$po->id] = true;
                    $postDateOf[$pr->id] = $po->date;
                    $postIdOf[$pr->id]   = $po->id;
                    unset($posts[$k]);                                    // คู่นี้ใช้แล้ว มารอบหน้าต้องจับคู่ใหม่
                    $posts = array_values($posts);
                    break;
                }
            }
        }

        return [$mergedPost, $postDateOf, $postIdOf];
    }

    /** จำนวนวันระหว่างวันที่ พ.ศ. 2 ค่า (Y-m-d) */
    private function daysBetweenBe(string $a, string $b): int
    {
        $toAd = function ($d) {
            if (!preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', $d, $m)) { return null; }
            return mktime(0, 0, 0, (int) $m[2], (int) $m[3], (int) $m[1] - 543);
        };
        $ta = $toAd($a); $tb = $toAd($b);
        if (!$ta || !$tb) { return PHP_INT_MAX; }
        return (int) round(($tb - $ta) / 86400);
    }

    /** บวกวันให้วันที่ พ.ศ. (Y-m-d) */
    private function addDaysBe(string $be, int $days): string
    {
        if (!preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', $be, $m)) { return $be; }
        $t = mktime(0, 0, 0, (int) $m[2], (int) $m[3] + $days, (int) $m[1] - 543);
        return (date('Y', $t) + 543) . date('-m-d', $t);
    }

    /**
     * ลงโรคประจำตัว B24 (HIV) ให้คนไข้ที่ผลเลือดบวก
     *
     * เติมต่อท้ายของเดิมเสมอ ไม่เขียนทับ — ช่องนี้มักมีโรคอื่นอยู่แล้ว (เบาหวาน/ความดัน)
     * ถ้าเขียนทับจะทำข้อมูลที่พยาบาลลงไว้หายทั้งช่อง
     * คืน true เมื่อเพิ่งเติมจริง (ใช้นับจำนวนไปบอกผู้ใช้)
     */
    private function tagB24(Patient $p): bool
    {
        $cur = trim((string) $p->congenitaldisease);

        // มีอยู่แล้วไม่ต้องเติมซ้ำ (เช็คแบบไม่สนตัวพิมพ์เล็กใหญ่ และต้องเป็นคำเดี่ยว ไม่ใช่ B240)
        if ($cur !== '' && preg_match('/\bB\s?24\b/i', $cur)) { return false; }

        // ค่าที่แปลว่า "ไม่มีโรคประจำตัว" ให้ถือเป็นช่องว่าง ไม่งั้นจะได้ "-, B24" หรือ "ปฏิเสธ, B24"
        $isBlank = $cur === '' || preg_match('/^(-+|ปฏิเสธ|ไม่มี|no|none|n\/a)$/iu', $cur);

        $p->congenitaldisease = $isBlank ? 'B24' : ($cur . ', B24');
        $p->save();

        return true;
    }

    /** แถวนี้มีข้อมูลที่พยาบาลกรอกเองไหม (ใช้ตัดสินว่าจะยุบทิ้งได้หรือเปล่า) */
    private function hasManualData(RihisVct $r): bool
    {
        foreach (['note', 'cd4', 'cd4_sent', 'refer_other', 'refer_hospital', 'repeat_date', 'referral_type', 'hospital1', 'form5'] as $f) {
            if ($r->$f !== null && $r->$f !== '') { return true; }
        }
        return false;
    }

    /**
     * แปลงสถานะคนไข้ -> [กลุ่มเป้าหมาย, กลุ่มย่อย MSW, รูปแบบ SW] ตามที่พี่อิ๋วกำหนด
     *
     *   patients.status : 1 MSM/MSW/TG · 2 พนักงานบริการ · 3 แรงงานข้ามชาติ
     *                     4 ผู้ป่วยเรือนจำ · 5 เยาวชน · 6 ประชาชนทั่วไป · 7 ผู้ใช้สารเสพติด
     *
     *   "อาชีพพิเศษ" (occupation = 10) คือตัวชี้ว่าเป็นผู้ให้บริการทางเพศ
     *     - พนักงานบริการ + อาชีพพิเศษ = FSW
     *     - MSM/TG        + อาชีพพิเศษ = MSW / TGSW (แยกด้วยกลุ่มย่อย)
     *   รูปแบบ SW: ลงชื่อร้าน/สถานที่ไว้ = Venue, ไม่ได้ลง = Non Venue
     *
     * กลุ่ม 8 (คู่ประจำของกลุ่มประชากรหลัก) ระบบเดาไม่ได้ ต้องให้พยาบาลเลือกเอง
     */
    private function mapTargetGroup($p): array
    {
        if (!$p) { return [null, null, null]; }

        $status = (int) $p->status;
        $isSw   = (int) $p->occupation === 10;          // อาชีพพิเศษ
        $hasVenue = trim((string) $p->organization) !== '';

        $sub = null;
        $swm = null;

        if ($isSw) {
            $group = 2;                                  // FSW/MSW/TGSW ใช้รหัสเดียวกัน (2)
            if ($status === 1) { $sub = 3; }             // MSM/TG + อาชีพพิเศษ -> MSW (พยาบาลแก้เป็น TGSW ได้)
            $swm = $hasVenue ? 1 : 2;                    // ลงร้าน/สถานที่ = Venue
        } else {
            $group = [
                1 => 1,   // MSM/MSW/TG      -> MSM
                2 => 2,   // พนักงานบริการ    -> FSW
                3 => 4,   // แรงงานข้ามชาติ   -> Migrants
                4 => 5,   // ผู้ป่วยเรือนจำ    -> Prisoner
                5 => 6,   // เยาวชน          -> เยาวชน
                6 => 7,   // ประชาชนทั่วไป    -> ประชาชนทั่วไป
                7 => 3,   // ผู้ใช้สารเสพติด   -> PWID
            ][$status] ?? null;
        }

        return [$group, $sub, $swm];
    }

    /**
     * สถานภาพสมรส: ของเรามี 4 แบบ ของ RIHIS มี 5 แบบ ต้องแปลงข้าม ไม่ใช่เลขเดียวกัน
     *   เรา 1 โสด · 2 หม้าย · 3 หย่า · 4 สมรส
     *   RIHIS 1 โสด · 2 มีคู่อยู่ด้วยกัน · 3 มีคู่แยกกันอยู่ · 4 หย่าร้าง · 5 หม้าย
     */
    private function mapMarital($p): ?int
    {
        return [1 => 1, 2 => 5, 3 => 4, 4 => 2][(int) ($p->marital_status ?? 0)] ?? null;
    }

    /**
     * สัญชาติ -> รหัส RIHIS (1 ไทย · 2 พม่า · 3 ลาว · 4 กัมพูชา · 5 อื่นๆ)
     *
     * ช่องนี้ในทะเบียนคนไข้เป็นข้อความพิมพ์อิสระ ข้อมูลจริงปนกันหมด
     * (ไทย/THA/TH, MMR/พม่า/เมียนมา, ชื่อประเทศภาษาอังกฤษ, และค่าที่กรอกผิดช่อง เช่น วันที่/ที่อยู่)
     * ค่าที่อ่านไม่ออกคืน null ดีกว่าเดา — จะได้ขึ้นเป็นช่องว่างให้พยาบาลเลือกเอง
     */
    private function mapNationality(?string $raw): ?int
    {
        $t = mb_strtolower(trim((string) $raw));
        if ($t === '') { return null; }

        // ค่าขยะ/กรอกผิดช่อง
        if (preg_match('#^[\-\*\s0]+$#u', $t)) { return null; }
        if (preg_match('#\d{1,2}/\d{1,2}/\d{4}#', $t)) { return null; }   // วันเกิดที่หลงมา
        if (mb_strlen($t) > 40) { return null; }                          // ที่อยู่ที่หลงมา
        if (preg_match('#^(n/a|์n/a|none|ไม่มี)$#u', $t)) { return null; }

        if (preg_match('#ไทย|^tha$|^th$|^thai#u', $t))                    { return 1; }
        if (preg_match('#พม่า|เมียนมา|แมียนมา|^mmr$|^mma$|myanmar|burm#u', $t) ) { return 2; }
        if (preg_match('#ลาว|^lao#u', $t))                                { return 3; }
        if (preg_match('#กัมพูชา|เขมร|^khm$|^cam|cambod#u', $t))          { return 4; }

        return 5;   // ต่างชาติอื่นๆ / ไร้สัญชาติ / ชาติพันธุ์
    }

    /**
     * ประวัติการตรวจ HIV (คอลัมน์ U)
     *   0 = ไม่เคยตรวจในชีวิต (ครั้งนี้เป็นครั้งแรก)
     *   1 = ใหม่ — เคยตรวจมาก่อน แต่เป็นครั้งแรกในรอบปีงบนี้
     *   2 = เก่า — ครั้งที่ 2 เป็นต้นไปในรอบปีงบนี้
     *
     * นับจากประวัติการตรวจ Anti-HIV ที่ห้องแล็บของเราเอง
     * (คนไข้ที่เคยตรวจที่อื่นมาก่อน ระบบไม่รู้ พยาบาลแก้เองได้)
     *
     * เก็บลงตารางเป็น "ข้อความ" ตามที่พี่อิ๋วขอ แล้วค่อยแปลงกลับเป็นรหัสตอน export
     */
    private const PAST_SERVICE_TEXT = [
        0 => 'ไม่เคยตรวจในชีวิต',
        1 => 'ใหม่ (ครั้งแรกในรอบปี)',
        2 => 'เก่า (ครั้งที่ 2 ขึ้นไปในรอบปี)',
    ];

    private function pastServiceText(array $history, string $visitDate, int $visitId, string $fyFrom): string
    {
        return self::PAST_SERVICE_TEXT[$this->pastService($history, $visitDate, $visitId, $fyFrom)];
    }

    /** ข้อความ -> รหัส 0/1/2 สำหรับใส่ในไฟล์ Excel (ฟอร์มส่วนกลางรับเป็นตัวเลข) */
    private function pastServiceCode($text): ?int
    {
        $t = trim((string) $text);
        if ($t === '') { return null; }
        if (is_numeric($t)) { return (int) $t; }

        if (mb_strpos($t, 'ไม่เคย') !== false) { return 0; }
        if (mb_strpos($t, 'ใหม่') !== false)   { return 1; }
        if (mb_strpos($t, 'เก่า') !== false)   { return 2; }
        return null;
    }

    private function pastService(array $history, string $visitDate, int $visitId, string $fyFrom): int
    {
        $earlierInFy = 0;
        $earlierEver = 0;

        foreach ($history as $h) {
            // ก่อนหน้า visit นี้จริงๆ (วันเดียวกันใช้ id ตัดสิน)
            $isEarlier = $h['date'] < $visitDate || ($h['date'] === $visitDate && $h['vid'] < $visitId);
            if (!$isEarlier) { continue; }

            $earlierEver++;
            if ($h['date'] >= $fyFrom) { $earlierInFy++; }
        }

        if ($earlierInFy > 0) { return 2; }
        return $earlierEver > 0 ? 1 : 0;
    }

    /** ข้อความผลแล็บ -> รหัส RIHIS (1 ลบ / 2 บวก / 3 ไม่แน่ชัด) */
    private function mapBloodResult(?string $text): ?int
    {
        $t = mb_strtolower(trim((string) $text));
        if ($t === '') { return null; }

        // ต้องเช็คคำปฏิเสธก่อน ไม่งั้น "non-reactive" จะไปเข้าเงื่อนไข "reactive"
        if (preg_match('/negative|non-?reactive|not detected|ไม่พบ/', $t)) { return 1; }
        if (preg_match('/positive|reactive|detected|พบเชื้อ/', $t))        { return 2; }
        return 3;
    }

    /** เพิ่มแถวเปล่า (ถ้าใส่ HN มา จะดึงข้อมูลคนไข้มาเติมให้เท่าที่ระบบรู้) */
    public function store(Request $request)
    {
        $data = [
            'service_date' => $this->toBeDate($request->input('service_date')) ?: $this->todayBe(),
            'pre_test'     => 1,
            'hiv_test'     => 1,
            'post_test'    => 0,
        ];

        $hn = trim((string) $request->input('hn'));
        if ($hn !== '') {
            $data['hn'] = $hn;
            $p = Patient::where('code', $hn)->first();
            if ($p) {
                $data['patient_id'] = $p->id;
                $data['name']       = $p->name;
                $data['surname']    = $p->surname;
                $data['id_card']    = $p->id_card_number;
                $data['sex']        = in_array((int) $p->sex, [1, 2], true) ? (int) $p->sex : null;
                $data['birth_date'] = $p->birth_date ?: null;
                $data['age']        = $this->ageFromBe($p->birth_date, $data['service_date']);
            }
        }

        $row = RihisVct::create($data);

        return redirect()->route('rihis-vct.index', $request->only(['from', 'to', 'per', 'q']))
            ->with('flash_message', 'เพิ่มแถวใหม่แล้ว' . ($hn !== '' && isset($p) && $p ? ' (ดึงข้อมูล HN ' . $hn . ' มาให้แล้ว)' : ''));
    }

    /** แก้ทีละช่องในตาราง (autosave) */
    public function save(Request $request)
    {
        $id    = (int) $request->input('id');
        $field = (string) $request->input('field');
        $value = $request->input('value');
        $value = ($value === '' ? null : $value);

        if (!in_array($field, self::SAVE_FIELDS, true)) {
            return response()->json(['ok' => false, 'msg' => 'ฟิลด์ไม่ถูกต้อง'], 422);
        }

        $row = RihisVct::find($id);
        if (!$row) {
            return response()->json(['ok' => false, 'msg' => 'ไม่พบแถวนี้'], 404);
        }

        // ช่องวันที่ในตารางกรอกเป็น ว/ด/ป พ.ศ. ต้องแปลงก่อนเก็บ
        if (in_array($field, ['service_date', 'birth_date', 'result_date', 'repeat_date'], true)) {
            $value = $this->toBeDate($value);
        }

        $row->$field = $value;

        // ฟังผลแล้วแต่ยังไม่ได้ลงวันฟังผล -> ใช้วันที่รับบริการให้ (ตรงกับสูตรในไฟล์ต้นฉบับ)
        if ($field === 'post_test' && (int) $value === 1 && !$row->result_date) {
            $row->result_date = $row->service_date;
        }
        // ยังไม่ฟังผล -> ล้างผลเลือด/วันฟังผล ไม่ให้ค้างข้อมูลที่ขัดกันเอง
        if ($field === 'post_test' && (int) $value === 0) {
            $row->result_date = null;
            $row->blood_result = null;
        }

        $row->save();

        return response()->json([
            'ok'      => true,
            'pending' => $row->pendingFields(),
            'row'     => ['result_date' => $this->thDate($row->result_date), 'blood_result' => $row->blood_result],
        ]);
    }

    public function destroy(Request $request, $id)
    {
        RihisVct::where('id', $id)->delete();

        return redirect()->route('rihis-vct.index', $request->only(['from', 'to', 'per', 'q']))
            ->with('flash_message', 'ลบแถวแล้ว');
    }

    /**
     * สรุปรายงาน — ตรงตามชีท "สรุปรายงาน" ของฟอร์มต้นฉบับ
     * (ในไฟล์ต้นฉบับสูตรเป็น #REF! ใช้ไม่ได้ จึงต้องคำนวณเองที่นี่)
     */
    private function summarize($rows): array
    {
        $groups = [
            'fsw'      => [2],       // ผู้ให้บริการทางเพศหญิง
            'msw'      => [3],       // ผู้ให้บริการทางเพศชาย (ดูจาก msm_sub)
            'msm'      => [1],       // ชายรักชาย
            'pwid'     => [3],
            'migrants' => [4],
            'youth'    => [6],
            'other'    => [7, 8],
        ];

        $mk = fn () => ['total' => 0, 'male' => 0, 'female' => 0,
                        'fsw' => 0, 'msw' => 0, 'msm' => 0, 'pwid' => 0,
                        'migrants' => 0, 'youth' => 0, 'other' => 0];

        $out = ['pre' => $mk(), 'test' => $mk(), 'post' => $mk(), 'positive' => $mk(), 'referred' => $mk()];

        foreach ($rows as $r) {
            $buckets = [];
            if ((int) $r->pre_test === 1)      { $buckets[] = 'pre'; }
            if ((int) $r->hiv_test === 1)      { $buckets[] = 'test'; }
            if ((int) $r->post_test === 1)     { $buckets[] = 'post'; }
            if ((int) $r->blood_result === 2)  { $buckets[] = 'positive'; }
            if ((int) $r->blood_result === 2 && (int) $r->refer_other === 1) { $buckets[] = 'referred'; }

            foreach ($buckets as $b) {
                $out[$b]['total']++;
                if ((int) $r->sex === 1) { $out[$b]['male']++; }
                if ((int) $r->sex === 2) { $out[$b]['female']++; }

                $g = (int) $r->target_group;
                if ($g === 2)      { $out[$b]['fsw']++; }
                elseif ($g === 1)  { (int) $r->msm_sub === 3 ? $out[$b]['msw']++ : $out[$b]['msm']++; }
                elseif ($g === 3)  { $out[$b]['pwid']++; }
                elseif ($g === 4)  { $out[$b]['migrants']++; }
                elseif ($g === 6)  { $out[$b]['youth']++; }
                elseif ($g)        { $out[$b]['other']++; }
            }
        }

        return $out;
    }

    /** ส่งออก Excel — ชีท Record วางคอลัมน์ตรงตำแหน่งฟอร์ม RIHIS + ชีทสรุปรายงาน */
    public function export(Request $request)
    {
        [$from, $to] = $this->range($request);
        $ss = $this->buildWorkbook($from, $to);

        $name = 'RIHIS-VCT_' . str_replace('-', '', $from) . '-' . str_replace('-', '', $to) . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Cache-Control: max-age=0');
        (new Xlsx($ss))->save('php://output');
        exit;
    }

    /** สร้างไฟล์ Excel (แยกจาก export เพื่อให้ทดสอบได้โดยไม่ต้องยิง output) */
    public function buildWorkbook(string $from, string $to): Spreadsheet
    {
        $rows = RihisVct::whereBetween('service_date', [$from, $to])
            ->orderBy('service_date')->orderBy('id')->get();

        $ss = new Spreadsheet();

        // ---------- ชีท Record ----------
        $sh = $ss->getActiveSheet();
        $sh->setTitle('Record RIHIS-VCT');

        // หัวตาราง: ใช้ตัวอักษรคอลัมน์เดียวกับฟอร์มต้นฉบับ เพื่อให้ก็อปวางข้ามไฟล์ได้ตรงช่อง
        $head = [
            'A' => 'วัน', 'B' => 'เดือน', 'C' => 'ปีพ.ศ.', 'E' => 'ชื่อ', 'F' => 'นามสกุล',
            'G' => 'UIC', 'H' => 'HN', 'I' => 'เลขบัตรประชาชน', 'J' => 'อายุ', 'K' => 'เพศ',
            'M' => 'สถานภาพสมรส', 'N' => 'สัญชาติ', 'O' => 'กลุ่มเป้าหมาย', 'P' => 'กลุ่มย่อย MSW',
            'Q' => 'รูปแบบ SW', 'R' => 'อาชีพ คนไทย', 'S' => 'อาชีพ ประชากรข้ามชาติ',
            'T' => 'ใบส่งต่อ', 'U' => 'ประวัติการตรวจ HIV',
            'V' => '1.การปรึกษาก่อนตรวจ', 'W' => '2.ตรวจหาการติดเชื้อ', 'X' => '3.การฟังผลเลือด',
            'Y' => 'วัน(ฟังผล)', 'Z' => 'เดือน(ฟังผล)', 'AA' => 'ปีพ.ศ.(ฟังผล)', 'AC' => '4.ผลเลือด',
            'AD' => 'ส่งต่อมาจาก', 'AE' => 'ประเภทหน่วยบริการ', 'AF' => 'ผู้แนะนำ',
            'AG' => 'รายละเอียดผู้แนะนำ', 'AH' => 'ส่งตรวจ CD4', 'AI' => 'ค่า CD4',
            'AJ' => 'นัดตรวจซ้ำ', 'AK' => 'วันโทรตาม', 'AL' => 'ส่งต่อรับบริการอื่น',
            'AM' => 'วิธีการตรวจ', 'AN' => 'การติดตาม', 'AQ' => 'ส่งตรวจ HIV ที่ 1',
            'AR' => 'หมายเหตุ', 'AS' => 'แบบส่งต่อ',
            // AV ในฟอร์มส่วนกลาง = "ระบุ สถานที่ส่งต่อไปรับบริการ (ชื่อโรงพยาบาลที่ส่งต่อ)"
            'AV' => 'สถานที่ส่งต่อไปรับบริการ', 'AY' => 'วันเกิด',
        ];
        foreach ($head as $col => $text) {
            $sh->setCellValue($col . '1', $text);
            $sh->getStyle($col . '1')->getFont()->setBold(true);
        }

        $r = 2;
        foreach ($rows as $row) {
            [$d, $m, $y]    = $this->splitBe($row->service_date);
            [$rd, $rm, $ry] = $this->splitBe($row->result_date);

            $sh->setCellValue('A' . $r, $d);
            $sh->setCellValue('B' . $r, $m);
            $sh->setCellValue('C' . $r, $y);
            $sh->setCellValueExplicit('E' . $r, (string) $row->name, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sh->setCellValueExplicit('F' . $r, (string) $row->surname, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sh->setCellValueExplicit('G' . $r, $row->uicText(), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            // HN / เลขบัตร ต้องเป็นข้อความ ไม่งั้น Excel ตัดศูนย์หน้าทิ้ง
            $sh->setCellValueExplicit('H' . $r, (string) $row->hn, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sh->setCellValueExplicit('I' . $r, (string) $row->id_card, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sh->setCellValue('J' . $r, $row->age);
            $sh->setCellValue('K' . $r, $row->sex);
            $sh->setCellValue('M' . $r, $row->marital);
            $sh->setCellValue('N' . $r, $row->nationality);
            $sh->setCellValue('O' . $r, $row->target_group);
            $sh->setCellValue('P' . $r, $row->msm_sub);
            $sh->setCellValue('Q' . $r, $row->sw_mode);
            $sh->setCellValue('R' . $r, $row->occ_thai);
            $sh->setCellValue('S' . $r, $row->occ_migrant);
            $sh->setCellValue('T' . $r, $row->referral);
            $sh->setCellValue('U' . $r, $this->pastServiceCode($row->past_service));
            $sh->setCellValue('V' . $r, $row->pre_test);
            $sh->setCellValue('W' . $r, $row->hiv_test);
            $sh->setCellValue('X' . $r, $row->post_test);
            $sh->setCellValue('Y' . $r, $rd);
            $sh->setCellValue('Z' . $r, $rm);
            $sh->setCellValue('AA' . $r, $ry);
            $sh->setCellValue('AC' . $r, $row->blood_result);
            $sh->setCellValueExplicit('AD' . $r, (string) $row->referral_unit, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sh->setCellValue('AE' . $r, $row->referral_type);
            $sh->setCellValue('AF' . $r, $row->referrer);
            $sh->setCellValueExplicit('AG' . $r, (string) $row->referrer_detail, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sh->setCellValue('AH' . $r, $row->cd4_sent);
            $sh->setCellValueExplicit('AI' . $r, (string) $row->cd4, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sh->setCellValue('AJ' . $r, $row->repeat_appt);
            $sh->setCellValueExplicit('AK' . $r, $this->thDate($row->repeat_date), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sh->setCellValue('AL' . $r, $row->refer_other);
            $sh->setCellValue('AM' . $r, $row->test_method);
            $sh->setCellValueExplicit('AN' . $r, (string) $row->followup, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sh->setCellValueExplicit('AQ' . $r, (string) $row->hospital1, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sh->setCellValueExplicit('AR' . $r, (string) $row->note, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sh->setCellValueExplicit('AS' . $r, (string) $row->form5, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sh->setCellValueExplicit('AV' . $r, (string) $row->refer_hospital, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sh->setCellValueExplicit('AY' . $r, $this->thDate($row->birth_date), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $r++;
        }
        foreach (array_keys($head) as $col) { $sh->getColumnDimension($col)->setAutoSize(true); }
        $sh->freezePane('A2');

        // ---------- ชีทสรุปรายงาน ----------
        $sum = $this->summarize($rows);
        $s2 = $ss->createSheet();
        $s2->setTitle('สรุปรายงาน');
        $cols = ['ลำดับ', 'กิจกรรม', 'หน่วยนับ', 'รวม', 'ช', 'ญ',
                 'ผู้ให้บริการทางเพศหญิง', 'ผู้ให้บริการทางเพศชาย', 'ชายรักชาย',
                 'ผู้ใช้ยาเสพติดชนิดฉีด', 'แรงงานข้ามชาติ', 'เยาวชน (10-24 ปี)', 'กลุ่มอื่นๆ'];
        $s2->fromArray($cols, null, 'A1');
        $s2->getStyle('A1:M1')->getFont()->setBold(true);

        $lines = [
            ['- Pre - test counseling', 'pre'],
            ['- HIV Testing', 'test'],
            ['- Post - test counseling', 'post'],
            ['- HIV Positive', 'positive'],
            ['- HIV Positive ได้รับการส่งต่อ', 'referred'],
        ];
        $r2 = 2;
        foreach ($lines as $i => [$label, $key]) {
            $v = $sum[$key];
            $s2->fromArray([
                $i + 1, $label, 'ราย', $v['total'], $v['male'], $v['female'],
                $v['fsw'], $v['msw'], $v['msm'], $v['pwid'], $v['migrants'], $v['youth'], $v['other'],
            ], null, 'A' . $r2);
            $r2++;
        }
        foreach (range('A', 'M') as $c) { $s2->getColumnDimension($c)->setAutoSize(true); }

        $ss->setActiveSheetIndex(0);

        return $ss;
    }

    // ===== helpers =====

    /** 'd/m/Y' (พ.ศ.) หรือ 'Y-m-d' -> 'Y-m-d' พ.ศ. ; ว่าง/อ่านไม่ออก = null */
    private function toBeDate($v): ?string
    {
        $v = trim((string) $v);
        if ($v === '' || $v === '0000-00-00') { return null; }
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $v, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        if (preg_match('#^\d{4}-\d{2}-\d{2}$#', $v)) { return $v; }
        return null;
    }

    private function thDate(?string $be): string
    {
        if (!$be || !preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', $be, $m)) { return ''; }
        return (int) $m[3] . '/' . (int) $m[2] . '/' . $m[1];
    }

    /** 'Y-m-d' พ.ศ. -> [วัน, เดือน, ปี] (ว่างคืน ['','','']) */
    private function splitBe(?string $be): array
    {
        if (!$be || !preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', $be, $m)) { return ['', '', '']; }
        return [(int) $m[3], (int) $m[2], (int) $m[1]];
    }

    private function todayBe(): string
    {
        return (now()->year + 543) . now()->format('-m-d');
    }

    private function ageFromBe(?string $birthBe, ?string $onBe): ?int
    {
        if (!$birthBe || !$onBe) { return null; }
        [$bd, $bm, $by] = $this->splitBe($birthBe);
        [$od, $om, $oy] = $this->splitBe($onBe);
        if (!$by || !$oy) { return null; }
        $age = $oy - $by;
        if ($om < $bm || ($om === $bm && $od < $bd)) { $age--; }
        return ($age >= 0 && $age < 130) ? $age : null;
    }
}
