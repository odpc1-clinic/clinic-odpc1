<?php

namespace App\Http\Controllers;

use App\Models\Diagnosi;
use App\Models\LabSub;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * ดึงรายชื่อคนไข้เองจากตัวกรองสำเร็จรูป — ไม่ต้องมาขอให้เขียนรายงานใหม่ทุกครั้ง
 *
 * ทำเมื่อ 2569-09-11 เพราะคำขอส่วนใหญ่หน้าตาเหมือนกันหมด:
 * "เอารายชื่อคนไข้ที่ผลตรวจ X เป็น Y ในช่วงเวลา Z" ต่างกันแค่ค่าในช่องว่าง
 * ตั้งใจให้เลือกจาก dropdown อย่างเดียว ไม่เปิดให้พิมพ์เงื่อนไขเอง
 * เพราะคนใช้คือพยาบาล/หมอ ไม่ใช่คนเขียนโปรแกรม และกันเผลอดึงข้อมูลผิด
 */
class ReportExploreController extends Controller
{
    /** ผลตรวจที่ให้เลือก — ค่าจริงในฐานมีแค่ Positive/Negative แต่เผื่อช่องว่างไว้ด้วย */
    public const RESULT_OPTIONS = [
        ''         => 'ทั้งหมด (ทุกผล)',
        'Positive' => 'Positive เท่านั้น',
        'Negative' => 'Negative เท่านั้น',
        '__any'    => 'มีผลแล้ว (ผลอะไรก็ได้)',
        '__none'   => 'ยังไม่มีผล',
    ];

    public const CLINICS = ['' => 'ทุกคลินิก', 1 => 'STI', 2 => 'TB', 3 => 'TMC'];

    /** ค้นจากอะไร — ผลตรวจ Lab (เดิม) หรือ การวินิจฉัยโรค (เพิ่ม 2569-10-01 พี่เดียร์ขอยอดหนองในย้อนหลัง) */
    public const MODES = ['lab' => 'ผลตรวจ Lab', 'dx' => 'การวินิจฉัยโรค'];

    /**
     * นับแบบไหน (โหมดวินิจฉัย)
     * new = ผู้ป่วยใหม่ + ติดเชื้อซ้ำ (= "ยอดผู้ป่วย" ที่ใช้รายงาน) · all = ทุกครั้งที่ลงวินิจฉัย รวมมาติดตาม/ปรึกษา
     */
    public const CASES = ['new' => 'ผู้ป่วยใหม่ + ติดเชื้อซ้ำ (ยอดผู้ป่วย)', 'all' => 'ทุกครั้งที่ลงวินิจฉัย (รวมมาติดตาม)'];
    public const NEW_CASE_STATES = [1, 2];   // Diagnosi::$diseaseStateOption: 1 ผู้ป่วยใหม่ · 2 เกิดโรคซ้ำ
    public const SEXES   = ['' => 'ทุกเพศ', 1 => 'ชาย', 2 => 'หญิง'];

    public const NATIONALITIES = [
        ''    => 'ทุกสัญชาติ',
        'th'  => 'ไทย',
        'mm'  => 'พม่า/เมียนมา',
        'etc' => 'สัญชาติอื่น',
    ];

    public function index(Request $request)
    {
        $filters = $this->filters($request);
        $rows    = collect();
        $ran     = $request->has('run');

        $summary = null;
        if ($ran) {
            $rows    = $this->query($filters)->limit(2000)->get();
            $summary = $this->summary($filters);
        }

        return view('report.explore', [
            'filters'   => $filters,
            'rows'      => $rows,
            'ran'       => $ran,
            'summary'   => $summary,
            'modes'     => self::MODES,
            'diseases'  => Diagnosi::$diseaseOption,
            'cases'     => self::CASES,
            'dxStates'  => Diagnosi::$diseaseStateOption,
            'methods'   => LabSub::$methodOption,
            'results'   => self::RESULT_OPTIONS,
            'clinics'   => self::CLINICS,
            'sexes'     => self::SEXES,
            'targets'   => Patient::$statusOption,
            'nations'   => self::NATIONALITIES,
        ]);
    }

    public function export(Request $request)
    {
        $filters = $this->filters($request);
        $rows    = $this->query($filters)->limit(10000)->get();

        $ss = new Spreadsheet();
        $sh = $ss->getActiveSheet();
        $sh->setTitle('รายชื่อคนไข้');

        // บรรทัดบนสุดบอกว่ากรองอะไรมา จะได้ไม่งงตอนเปิดไฟล์ทีหลัง
        $sh->setCellValue('A1', 'เงื่อนไขที่ใช้ดึง: ' . $this->describe($filters) . ' · ดึงเมื่อ ' . now()->format('d/m/Y H:i') . ' น.');
        $sh->mergeCells('A1:J1');
        $sh->getStyle('A1')->getFont()->setBold(true);

        $dx = $filters['mode'] === 'dx';
        $sh->fromArray(['ลำดับ', 'วันที่', 'HN', 'ชื่อ-นามสกุล', 'เพศ', 'อายุ', 'สัญชาติ', 'โทรศัพท์',
                        $dx ? 'การวินิจฉัย' : 'รายการตรวจ', $dx ? 'สถานะ' : 'ผล'], null, 'A3');
        $sh->getStyle('A3:J3')->getFont()->setBold(true);

        $i = 4; $n = 1;
        foreach ($rows as $r) {
            $sh->fromArray([
                $n++, $this->beDate($r->visit_date), $r->hn ?: '-', trim($r->name . ' ' . $r->surname),
                self::SEXES[$r->sex] ?? '-', $this->age($r->birth_date, $r->visit_date),
                $r->nationality ?: '-', $r->tel ?: '-',
                $dx ? $this->dxText($r) : (LabSub::$methodOption[$r->method] ?? '-'),
                $dx ? (Diagnosi::$diseaseStateOption[$r->disease_state] ?? '-') : ($r->result ?: 'ยังไม่มีผล'),
            ], null, 'A' . $i++);
        }

        foreach (range('A', 'J') as $c) { $sh->getColumnDimension($c)->setAutoSize(true); }
        $sh->freezePane('A4');

        $name = 'รายชื่อคนไข้_' . now()->format('Ymd_Hi') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename*=UTF-8\'\'' . rawurlencode($name));
        header('Cache-Control: max-age=0');
        (new Xlsx($ss))->save('php://output');
        exit;
    }

    /** อ่านค่ากรองจาก request แล้วเติมค่าเริ่มต้น (วันที่เก็บเป็น พ.ศ.) */
    private function filters(Request $request): array
    {
        $beThisYear = (int) date('Y') + 543;

        // บังคับเป็น string ทุกตัว — ถ้าปล่อยให้เป็น null แล้วเทียบ !== '' จะผ่านเงื่อนไข
        // แล้ว Laravel จะแปลง where('col', null) เป็น "col is null" ให้เอง
        // กลายเป็นกรองผิดแบบเงียบ ๆ (เลือก "ทุกผล" แต่ได้เฉพาะรายการที่ยังไม่มีผล — เจอ 2569-09-11)
        $str = fn ($key, $default = '') => trim((string) ($request->get($key) ?? $default));

        $from = $this->toBe($request->get('from')) ?: ($beThisYear - 1) . '-10-01';
        $to   = $this->toBe($request->get('to'))   ?: $beThisYear . date('-m-d');
        // ใส่วันที่กลับด้าน (ถึง < ตั้งแต่) ได้ 0 แถวแบบงง ๆ — สลับให้เอง
        if ($from > $to) { [$from, $to] = [$to, $from]; }

        $mode = $str('mode', 'lab');
        $case = $str('case', 'new');

        return [
            'mode'    => array_key_exists($mode, self::MODES) ? $mode : 'lab',
            'disease' => $str('disease'),
            'case'    => array_key_exists($case, self::CASES) ? $case : 'new',
            'from'    => $from,
            'to'      => $to,
            'method'  => $str('method'),
            'result'  => $request->has('result') ? $str('result') : 'Positive',
            'clinic'  => $str('clinic'),
            'sex'     => $str('sex'),
            'target'  => $str('target'),
            'nation'  => $str('nation'),
            'age_min' => $str('age_min'),
            'age_max' => $str('age_max'),
        ];
    }

    /** รับวันที่จาก <input type=date> (ค.ศ.) แปลงเป็น พ.ศ. ให้ตรงกับที่เก็บในฐาน */
    private function toBe(?string $ad): ?string
    {
        if (!$ad) { return null; }
        try {
            return \Carbon\Carbon::parse($ad)->addYears(543)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** visits.date เก็บ พ.ศ. แต่ข้อมูลยุคแรก (มิ.ย. 2565) เก็บ ค.ศ. — กรองให้ครอบทั้งสองแบบ ไม่งั้นตกหล่นเงียบ ๆ */
    private function whereVisitDate($q, array $f): void
    {
        $ad = fn ($be) => ((int) substr($be, 0, 4) - 543) . substr($be, 4);
        $q->where(fn ($w) => $w->whereBetween('v.date', [$f['from'], $f['to']])
            ->orWhere(fn ($x) => $x->where('v.date', '<', '2500-01-01')->whereBetween('v.date', [$ad($f['from']), $ad($f['to'])])));
    }

    /** ตัวกรองที่ใช้ร่วมกันทั้งสองโหมด (คลินิก เพศ กลุ่ม สัญชาติ อายุ) */
    private function commonFilters($q, array $f): void
    {
        if ($f['clinic'] !== '') { $q->where('v.clinic', $f['clinic']); }
        if ($f['sex'] !== '')    { $q->where('p.sex', $f['sex']); }
        if ($f['target'] !== '') { $q->where('p.status', $f['target']); }

        if ($f['nation'] === 'th') {
            $q->whereIn('p.nationality', ['THA', 'Thai', 'ไทย']);
        } elseif ($f['nation'] === 'mm') {
            $q->whereIn('p.nationality', ['MMR', 'Myanmar', 'พม่า']);
        } elseif ($f['nation'] === 'etc') {
            $q->where(function ($w) {
                $w->whereNotIn('p.nationality', ['THA', 'Thai', 'ไทย', 'MMR', 'Myanmar', 'พม่า'])
                  ->orWhereNull('p.nationality');
            });
        }

        // อายุคิดจากปีที่มาตรวจลบปีเกิด (ทั้งคู่เก็บเป็น พ.ศ. ในฐานอยู่แล้ว)
        // patients.birth_date เป็นคอลัมน์ชนิด DATE — ห้ามเทียบกับค่าว่าง '' เพราะ MySQL แปลงไม่ได้
        // แล้วจะตัดทุกแถวทิ้งเงียบ ๆ (ตัวกรองอายุได้ 0 เสมอ — เจอ 2569-09-11)
        if ($f['age_min'] !== '' || $f['age_max'] !== '') {
            $expr = 'CAST(LEFT(p.birth_date,4) AS SIGNED)';
            $q->whereNotNull('p.birth_date')->whereRaw("$expr > 0");

            // visit ยุคแรกเก็บปี ค.ศ. ต้องบวก 543 ก่อนลบปีเกิด
            $vy  = 'IF(CAST(LEFT(v.date,4) AS SIGNED) < 2500, CAST(LEFT(v.date,4) AS SIGNED) + 543, CAST(LEFT(v.date,4) AS SIGNED))';
            $age = "$vy - $expr";
            if ($f['age_min'] !== '') { $q->whereRaw("$age >= ?", [(int) $f['age_min']]); }
            if ($f['age_max'] !== '') { $q->whereRaw("$age <= ?", [(int) $f['age_max']]); }
        }
    }

    /** โหมดวินิจฉัยโรค — หนึ่งแถว = ใบวินิจฉัยหนึ่งรายการ */
    private function dxQuery(array $f)
    {
        $q = DB::table('diagnoses as d')
            ->join('visits as v', 'v.id', '=', 'd.visit_id')
            ->leftJoin('patients as p', 'p.id', '=', 'v.patient_id')
            ->select([
                'p.code as hn', 'p.name', 'p.surname', 'p.sex', 'p.birth_date',
                'p.nationality', 'p.tel', 'p.status as target', 'v.patient_id',
                'v.date as visit_date', 'v.clinic', 'v.id as visit_id',
                'd.disease', 'd.term_syphilis', 'd.other_disease', 'd.disease_state',
            ])
            ->orderBy('v.date');
        $this->whereVisitDate($q, $f);
        if ($f['disease'] !== '') { $q->where('d.disease', $f['disease']); }
        if ($f['case'] === 'new') { $q->whereIn('d.disease_state', self::NEW_CASE_STATES); }
        $this->commonFilters($q, $f);

        return $q;
    }

    private function query(array $f)
    {
        if ($f['mode'] === 'dx') { return $this->dxQuery($f); }

        $q = DB::table('lab_subs as ls')
            ->join('labs as l', 'l.id', '=', 'ls.lab_id')
            ->join('visits as v', 'v.id', '=', 'l.visit_id')
            ->leftJoin('patients as p', 'p.id', '=', 'v.patient_id')
            ->select([
                'p.code as hn', 'p.name', 'p.surname', 'p.sex', 'p.birth_date',
                'p.nationality', 'p.tel', 'p.status as target',
                'v.date as visit_date', 'v.clinic', 'v.id as visit_id', 'v.patient_id',
                'ls.method', 'ls.result', 'l.approve_by',
            ])
            ->orderBy('v.date');
        $this->whereVisitDate($q, $f);

        if ($f['method'] !== '') { $q->where('ls.method', $f['method']); }

        // ผลตรวจ
        if ($f['result'] === '__any') {
            $q->whereNotNull('ls.result')->where('ls.result', '!=', '');
        } elseif ($f['result'] === '__none') {
            $q->where(fn ($w) => $w->whereNull('ls.result')->orWhere('ls.result', ''));
        } elseif ($f['result'] !== '') {
            $q->where('ls.result', $f['result']);
        }

        $this->commonFilters($q, $f);

        return $q;
    }

    public function age($birthDate, $visitDate)
    {
        if (!$birthDate || !$visitDate) { return '-'; }
        $by = (int) substr((string) $birthDate, 0, 4);
        $vy = (int) substr($this->beDate($visitDate), 0, 4);

        return ($by > 0 && $vy >= $by) ? $vy - $by : '-';
    }

    /** วันที่ visit เป็น พ.ศ. เสมอ (ข้อมูลยุคแรกเก็บ ค.ศ.) */
    public function beDate($date): string
    {
        $y = (int) substr((string) $date, 0, 4);
        return ($y > 0 && $y < 2500 ? $y + 543 : $y) . substr((string) $date, 4, 6);
    }

    /** ข้อความการวินิจฉัย: Syphilis บอกระยะ · Other บอกข้อความที่พิมพ์ */
    public function dxText($r): string
    {
        $name = Diagnosi::$diseaseOption[$r->disease] ?? ('โรครหัส ' . $r->disease);
        if ((int) $r->disease === 1 && !empty(Diagnosi::$termSyphilisOption[$r->term_syphilis] ?? '')) {
            return $name . ' ระยะ ' . Diagnosi::$termSyphilisOption[$r->term_syphilis];
        }
        if ((int) $r->disease === 11 && trim((string) $r->other_disease) !== '') {
            return $name . ': ' . mb_substr(trim($r->other_disease), 0, 60);
        }
        return $name;
    }

    /**
     * สรุปยอดของเงื่อนไขทั้งหมด (ไม่จำกัด 2,000 แถวแบบตาราง)
     * ตอบคำถาม "ยอดผู้ป่วย X ย้อนหลัง N ปี" ได้ทันที: คนไม่ซ้ำ · จำนวนครั้ง (visit) · ชาย/หญิง · แยกปีงบประมาณ
     */
    public function summary(array $f): array
    {
        $rows = (clone $this->query($f))->reorder()->select(['v.patient_id', 'v.id as visit_id', 'v.date as visit_date', 'p.sex'])->get();
        $fy = function ($d) { $d = $this->beDate($d); $y = (int) substr($d, 0, 4); return (int) substr($d, 5, 2) >= 10 ? $y + 1 : $y; };
        $pack = function ($list) {
            $people = $list->unique('patient_id');
            return [
                'people' => $people->count(),
                'visits' => $list->unique('visit_id')->count(),
                'male'   => $people->where('sex', 1)->count(),
                'female' => $people->where('sex', 2)->count(),
            ];
        };

        return [
            'total'  => $pack($rows),
            'byYear' => $rows->groupBy(fn ($r) => $fy($r->visit_date))->sortKeys()->map($pack)->all(),
        ];
    }

    /** สรุปเงื่อนไขเป็นข้อความ ใช้โชว์บนหน้าจอและหัวไฟล์ Excel */
    public function describe(array $f): string
    {
        $th = fn ($d) => \App\Utillity::db2thDate($d);
        $parts = ['ช่วง ' . $th($f['from']) . ' ถึง ' . $th($f['to'])];

        if ($f['mode'] === 'dx') {
            $parts[] = 'การวินิจฉัย ' . ($f['disease'] !== '' ? (Diagnosi::$diseaseOption[$f['disease']] ?? $f['disease']) : 'ทุกโรค');
            $parts[] = self::CASES[$f['case']] ?? '';
        } else {
            if ($f['method'] !== '') { $parts[] = 'รายการ ' . (LabSub::$methodOption[$f['method']] ?? $f['method']); }
            if ($f['result'] !== '') { $parts[] = 'ผล ' . (self::RESULT_OPTIONS[$f['result']] ?? $f['result']); }
        }
        if ($f['clinic'] !== '') { $parts[] = 'คลินิก ' . (self::CLINICS[$f['clinic']] ?? ''); }
        if ($f['sex'] !== '')    { $parts[] = self::SEXES[$f['sex']] ?? ''; }
        if ($f['target'] !== '') { $parts[] = 'กลุ่ม ' . (Patient::$statusOption[$f['target']] ?? ''); }
        if ($f['nation'] !== '') { $parts[] = self::NATIONALITIES[$f['nation']] ?? ''; }
        if ($f['age_min'] !== '' || $f['age_max'] !== '') {
            $parts[] = 'อายุ ' . ($f['age_min'] !== '' ? $f['age_min'] : '0') . '-' . ($f['age_max'] !== '' ? $f['age_max'] : '∞') . ' ปี';
        }

        return implode(' · ', $parts);
    }
}
