<?php

namespace App\Services;

use App\Enums\PatientRiskGroup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * รายงาน ก.1 (ฉบับปรับปรุง พ.ศ.2555) ของคลินิก STI — แทนการนับมือลงไฟล์ "ก.1 เดือน ปี.xlsx" (คลินิกขอ 2569-11-10)
 *
 * ทุกช่องนับเป็น "คนไม่ซ้ำ" ในเดือน (ยกเว้น 3.3 = ครั้ง และข้อ 4.2/4.4 = จำนวนคู่สัมผัส)
 * กลุ่มเป้าหมายเป็นกลุ่มเดียวต่อคน ช่อง 7 กลุ่มบวกกันได้เท่ากับรวม (ตรงกับไฟล์เดิม):
 *   กลุ่มเสี่ยง MSM/MSW/TG = MSM เสมอ (คลินิกให้ MSM เป็นหลัก) · พนักงานบริการ หญิง = FSW / ชาย = MSW
 *   ผู้ใช้สารเสพติด = IDU · แรงงานข้ามชาติ · เยาวชน = กลุ่มเสี่ยงเยาวชน หรือคนที่เหลือที่อายุ 10–24 ปี · ที่เหลือ = กลุ่มอื่นๆ
 * ผู้ป่วย = วินิจฉัยสถานะ ผู้ป่วยใหม่/เกิดโรคซ้ำ (เกณฑ์เดียวกับรายงานโรคอื่นในระบบ)
 *
 * พยาบาลแก้ตัวเลขทับได้ทุกช่อง (เก็บใน configs ทีละช่อง) — ดู StiK1ReportController
 */
class StiK1Report
{
    public const COLS = ['total', 'm', 'f', 'fsw', 'msw', 'msm', 'idu', 'migrant', 'youth', 'other'];

    public const GROUP_LABELS = [
        'fsw' => 'พนักงานบริการหญิง', 'msw' => 'พนักงานบริการชาย', 'msm' => 'ชายที่มีเพศสัมพันธ์กับชาย',
        'idu' => 'ผู้ใช้ยาเสพติดชนิดฉีด', 'migrant' => 'แรงงานข้ามชาติ', 'youth' => 'เยาวชน (10-24 ปี)', 'other' => 'กลุ่มอื่นๆ',
    ];

    /**
     * โรคติดต่อทางเพศสัมพันธ์หลัก = กามโรค 5 โรค + พยาธิช่องคลอด (ตามหมายเหตุท้ายแบบ ก.1)
     * อื่นๆ = เริม หูดหงอนไก่ หูดข้าวสุก เชื้อรา ช่องคลอดอักเสบ (Sheet2 ของไฟล์เดิม)
     * id ตาม Diagnosi::$diseaseOption
     */
    public const MAIN_DISEASES = [
        'gc'   => ['หนองใน', [2]],
        'ngu'  => ['หนองในเทียม', [3, 19]],
        'syph' => ['ซิฟิลิส', [1]],
        'chan' => ['แผลริมอ่อน', [5]],
        'lgv'  => ['กามโรคของต่อมและท่อน้ำเหลือง', [4]],
        'tv'   => ['พยาธิช่องคลอด', [8]],
    ];
    public const OTHER_DISEASES = [
        'wart' => ['หูดหงอนไก่', [10]],
        'hsv'  => ['เริม', [12]],
        'mc'   => ['หูดข้าวสุก', [9]],
        'cand' => ['เชื้อรา', [7]],
        'bv'   => ['ช่องคลอดอักเสบ', [6]],
    ];

    private const CASE_STATES = [1, 2];
    // ContactSTI: สถานะที่ถือว่าติดตามได้
    private const FU_OK = ['F/U แล้ว', 'โทรตาม หายแล้ว', 'F/U ที่ซายูริ'];
    // คู่สัมผัส: วิธีติดตามที่ถือว่ามีข้อมูลพอ/ติดตามได้ (ContactPerson::$touchTracingOption · 6 = ติดตามไม่ได้)
    private const TRACE_OK = [1, 2, 3, 4, 5, 7];

    /** @var Collection patient_id => ['sex','group'] */
    private $who;

    /** คืน ['sheet1' => rows, 'sheet2' => rows] — row = ['key','no','label','unit','sub','vals'=>[col=>n]|null] */
    public function build(string $from, string $to): array
    {
        $visits = DB::table('visits as v')->join('patients as p', 'p.id', '=', 'v.patient_id')
            ->where('v.clinic', 1)->whereBetween('v.date', [$from, $to])
            ->get(['v.id', 'v.patient_id', 'v.date', 'p.sex', 'p.status', 'p.occupation', 'p.birth_date']);
        $vIds = $visits->pluck('id')->all();

        // กลุ่มของคน ณ visit แรกของเดือน (อายุคิด ณ วันนั้น)
        $this->who = $visits->sortBy('date')->unique('patient_id')->keyBy('patient_id')->map(fn ($v) => [
            'sex'   => (int) $v->sex,
            'group' => $this->groupOf($v),
        ]);
        $visitPid = $visits->pluck('patient_id', 'id');

        $dx = $vIds ? DB::table('diagnoses')->whereIn('visit_id', $vIds)->get(['visit_id', 'disease', 'disease_state']) : collect();
        $cases = $dx->filter(fn ($d) => in_array((int) $d->disease_state, self::CASE_STATES, true));
        $pidsOf = fn (array $ids) => $cases->filter(fn ($d) => in_array((int) $d->disease, $ids, true))
            ->map(fn ($d) => $visitPid[$d->visit_id] ?? null)->filter()->unique()->values();
        $mainIds  = array_merge(...array_column(array_values(self::MAIN_DISEASES), 1));
        $otherIds = array_merge(...array_column(array_values(self::OTHER_DISEASES), 1));
        $main  = $pidsOf($mainIds);
        $other = $pidsOf($otherIds);
        // 3.2 / 5 / "พบโรค" = หลัก + อื่นๆ บวกกัน (คนที่เป็นทั้งสองแบบนับ 2) ตามที่คลินิกนับในไฟล์เดิม (เม.ย. 67: 40 + 16 = 56)
        $sick  = $main->merge($other)->values();

        $labs = $vIds ? DB::table('lab_subs as s')->join('labs as l', 'l.id', '=', 's.lab_id')
            ->whereIn('l.visit_id', $vIds)->get(['l.visit_id', 's.method', 's.result']) : collect();
        $labPids = fn ($fn) => $labs->filter($fn)->map(fn ($r) => $visitPid[$r->visit_id] ?? null)->filter()->unique()->values();
        $syphTest = $labPids(fn ($r) => in_array((int) $r->method, [7, 8], true));   // TP syphilis / RPR
        $hivTest  = $labPids(fn ($r) => (int) $r->method === 6);
        // ผลในฐานเก็บเป็นข้อความ ("Positive") ไม่ใช่รหัสใน $hivResultOption · Pap พิมพ์อิสระ จับคำว่าผิดปกติ
        $hivPos   = $labPids(fn ($r) => (int) $r->method === 6 && in_array(strtolower(trim((string) $r->result)), ['positive', '1'], true));
        $papAbn   = $labPids(fn ($r) => (int) $r->method === 13 && (string) $r->result !== ''
            && preg_match('/abnormal|\b(ASC|ASC-US|ASC-H|LSIL|HSIL|AGC|HPV)\b|^2$/i', (string) $r->result));
        $dxPids   = fn (int $id) => $dx->where('disease', $id)->map(fn ($d) => $visitPid[$d->visit_id] ?? null)->filter()->unique()->values();

        // ---- ข้อ 4 ติดตาม: เคสโรคหลักในเดือน + Pap ผิดปกติ ----
        $caseVisits = $cases->filter(fn ($d) => in_array((int) $d->disease, $mainIds, true))->pluck('visit_id')->unique()->values();
        $followIds = $main->merge($papAbn)->unique()->values();
        $fuStatus = $caseVisits->isNotEmpty()
            ? DB::table('contact_sti')->whereIn('visit_id', $caseVisits)->pluck('fu_status', 'visit_id') : collect();
        // ติดตามได้ = มีครั้งถัดไปมาที่คลินิกแล้ว หรือ ContactSTI ลงว่าติดตามได้
        $later = $followIds->isNotEmpty() ? DB::table('visits')->where('clinic', 1)->whereIn('patient_id', $followIds)
            ->where('date', '>', $from)->get(['patient_id', 'date'])->groupBy('patient_id') : collect();
        $firstCaseDate = [];
        foreach ($visits as $v) {
            if ($followIds->contains($v->patient_id) && (! isset($firstCaseDate[$v->patient_id]) || $v->date < $firstCaseDate[$v->patient_id])) {
                $firstCaseDate[$v->patient_id] = $v->date;
            }
        }
        $fuOkVisitPids = $fuStatus->filter(fn ($s) => in_array($s, self::FU_OK, true))->keys()->map(fn ($id) => $visitPid[$id] ?? null)->filter();
        $followed = $followIds->filter(fn ($pid) => $fuOkVisitPids->contains($pid)
            || ($later[$pid] ?? collect())->contains(fn ($x) => $x->date > ($firstCaseDate[$pid] ?? $to)))->values();

        // คู่สัมผัสของเคสโรคหลัก — เพศตามคู่สัมผัส กลุ่มตามผู้ป่วย
        $contacts = $caseVisits->isNotEmpty()
            ? DB::table('contact_people')->whereIn('visit_id', $caseVisits)->get(['visit_id', 'cpsex', 'trush_tracks']) : collect();
        $enough = $contacts->filter(fn ($c) => in_array((int) $c->trush_tracks, self::TRACE_OK, true));

        $r = fn ($key, $no, $label, $unit, $vals, $sub = false) => compact('key', 'no', 'label', 'unit', 'vals', 'sub');
        $na = null;   // ระบบไม่มีข้อมูล — กรอกเอง

        $sheet1 = [
            $r('h1', '1', 'การตรวจเลือดเพื่อค้นหาผู้ป่วย/ติดเชื้อโรคซิฟิลิส', '', 'head'),
            $r('1.1', '1.1', 'ผู้มารับบริการที่ได้รับการตรวจเลือดเพื่อค้นหาโรคซิฟิลิสในหน่วยบริการ', 'ราย', $this->count($syphTest)),
            $r('1.1a', '', '- พบเป็นโรคซิฟิลิสรายใหม่', 'ราย', $this->count($pidsOf([1])), true),
            $r('1.2', '1.2', 'หญิงมีครรภ์ที่ได้รับการตรวจเลือดเพื่อค้นหาโรคซิฟิลิสในหน่วยฝากครรภ์', 'ราย', $na),
            $r('1.2a', '', '- พบเป็นโรคซิฟิลิสรายใหม่', 'ราย', $na, true),
            $r('1.3', '1.3', 'ผู้ที่ได้รับบริการเชิงรุกและตรวจเลือดค้นหาโรคซิฟิลิส/การติดเชื้อเอชไอวี', 'ราย', $na),
            $r('1.3a', '', '- พบเป็นโรคซิฟิลิส', 'ราย', $na, true),
            $r('1.3b', '', '- พบผลบวกเอชไอวี', 'ราย', $na, true),
            $r('h2', '2', 'การตรวจเลือดเพื่อหาการติดเชื้อ เอชไอวี ในผู้มารับบริการคัดกรอง หรือผู้ป่วยโรคติดต่อทางเพศสัมพันธ์', '', 'head'),
            $r('2a', '', '- Pre - test counseling', 'ราย', $this->count($dxPids(13)), true),
            $r('2b', '', '- HIV Testing', 'ราย', $this->count($hivTest), true),
            $r('2c', '', '- Post - test counseling', 'ราย', $this->count($dxPids(14)), true),
            $r('2d', '', '- HIV Positive', 'ราย', $this->count($hivPos), true),
            $r('2e', '', '- HIV Positive ได้รับการส่งต่อ', 'ราย', $na, true),
            $r('h3', '3', 'การคัดกรอง/ดูแลรักษาผู้ป่วยโรคติดต่อทางเพศสัมพันธ์', '', 'head'),
            $r('3.1', '3.1', 'จำนวนผู้มารับบริการคัดกรองโรคติดต่อทางเพศสัมพันธ์', 'ราย', $this->count($this->who->keys())),
            $r('3.1a', '', '- พบป่วยโรคติดต่อทางเพศสัมพันธ์หลัก*', 'ราย', $this->count($main), true),
            $r('3.1b', '', '- พบป่วยโรคติดต่อทางเพศสัมพันธ์อื่นๆ**', 'ราย', $this->count($other), true),
            $r('3.2', '3.2', 'จำนวนผู้ป่วยโรคติดต่อทางเพศสัมพันธ์', 'ราย', $this->count($sick, false)),
            $r('3.2a', '', '- พบป่วยโรคติดต่อทางเพศสัมพันธ์หลัก*', 'ราย', $this->count($main), true),
            $r('3.2b', '', '- พบป่วยโรคติดต่อทางเพศสัมพันธ์อื่นๆ**', 'ราย', $this->count($other), true),
            $r('3.3', '3.3', 'จำนวนครั้งของผู้มารับบริการคัดกรอง/ตรวจโรคติดต่อทางเพศสัมพันธ์', 'ครั้ง', $this->count($visits->pluck('patient_id'), false)),
            $r('h4', '4', 'การติดตามผู้ป่วยและผู้สัมผัสโรคติดต่อทางเพศสัมพันธ์หลัก', '', 'head'),
            $r('4.1', '4.1', 'ผู้ป่วยโรคติดต่อทางเพศสัมพันธ์หลักและผู้ติดเชื้อ HPV จากผล Pap Smear ที่จะต้องติดตาม', 'ราย', $this->count($followIds)),
            $r('4.1a', '', '- ติดตามได้', 'ราย', $this->count($followed), true),
            $r('4.2', '4.2', 'ผู้สัมผัสโรคทั้งหมดที่ได้จากการสอบสวนโรคจากผู้ป่วย', 'คน', $this->contactCount($contacts, $visitPid)),
            $r('4.3', '4.3', 'ผู้สัมผัสโรคที่ได้รับแจ้งจากหน่วยงานอื่น', 'คน', $na),
            $r('4.4', '4.4', 'ผู้สัมผัสโรคที่มีข้อมูลเพียงพอที่จะติดตาม', 'คน', $this->contactCount($enough, $visitPid)),
            $r('4.4a', '', '- ติดตามได้', 'คน', $this->contactCount($enough, $visitPid), true),
            $r('5', '5', 'การให้การปรึกษาทางการแพทย์และสังคมแก่ผู้ป่วยโรคติดต่อทางเพศสัมพันธ์', 'ราย', $this->count($sick, false)),
            $r('h6', '6', 'การให้สุขศึกษาและเผยแพร่ความรู้โรคติดต่อทางเพศสัมพันธ์', '', 'head'),
            $r('6.1', '6.1', '- ให้สุขศึกษาในสถานบริการสาธารณสุข', 'คน', $this->count($this->who->keys()), true),
            $r('6.2', '6.2', '- ให้สุขศึกษานอกสถานบริการสาธารณสุข (งานบริการเชิงรุก)', 'คน', $na, true),
            $r('h7', '7', 'การควบคุมแหล่งบริการทางเพศ', '', 'head'),
            $r('7a', '', '- จำนวน แหล่งบริการทางเพศที่ควบคุมได้ (แหล่งใหม่)', 'แห่ง', $na, true),
            $r('7b', '', '- จำนวน แหล่งบริการทางเพศที่ควบคุมได้ (แหล่งเก่า)', 'แห่ง', $na, true),
        ];

        $sheet2 = [
            $r('s.all', '', 'พบโรคติดต่อทางเพศสัมพันธ์', 'ราย', $this->count($sick, false)),
            $r('s.main', '', 'พบโรคติดต่อทางเพศสัมพันธ์หลัก*', 'ราย', $this->count($main)),
        ];
        foreach (self::MAIN_DISEASES as $k => [$label, $ids]) {
            $sheet2[] = $r('s.' . $k, '', $label, 'ราย', $this->count($pidsOf($ids)), true);
        }
        $sheet2[] = $r('s.other', '', 'พบโรคติดต่อทางเพศสัมพันธ์อื่นๆ**', 'ราย', $this->count($other));
        foreach (self::OTHER_DISEASES as $k => [$label, $ids]) {
            $sheet2[] = $r('s.' . $k, '', $label, 'ราย', $this->count($pidsOf($ids)), true);
        }
        $sheet2[] = $r('s.visit', '', 'การเยี่ยมแหล่งแพร่', 'ครั้ง/แห่ง', $na);
        $sheet2[] = $r('s.study', '', 'การศึกษาดูงาน', 'ครั้ง/คน', $na);

        return ['sheet1' => $sheet1, 'sheet2' => $sheet2];
    }

    /** กลุ่มเป้าหมายกลุ่มเดียวต่อคน (ลำดับความสำคัญตามที่คลินิกกำหนด 2569-11-10) */
    private function groupOf($v): string
    {
        $status = (int) $v->status;
        if ($status === PatientRiskGroup::MsmTg->value) { return 'msm'; }
        if ($status === PatientRiskGroup::SexWorker->value) { return (int) $v->sex === 1 ? 'msw' : 'fsw'; }
        if ($status === PatientRiskGroup::DrugUser->value) { return 'idu'; }
        if ($status === PatientRiskGroup::Migrant->value) { return 'migrant'; }
        if ($status === PatientRiskGroup::Youth->value) { return 'youth'; }
        $age = self::ageOn((string) $v->birth_date, (string) $v->date);
        return ($age !== null && $age >= 10 && $age <= 24) ? 'youth' : 'other';
    }

    /** อายุเต็มปี — วันเกิดและวันมาเก็บ พ.ศ. (ข้อมูลเก่าบางแถวเป็น ค.ศ. ปรับให้ตรงกันก่อน) */
    public static function ageOn(string $birth, string $on): ?int
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $birth, $b) || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $on, $o)) { return null; }
        $by = (int) $b[1]; $oy = (int) $o[1];
        if ($by < 2400) { $by += 543; }
        if ($oy < 2400) { $oy += 543; }
        if ($by < 2400 || $by > $oy) { return null; }
        return $oy - $by - (($o[2] . $o[3]) < ($b[2] . $b[3]) ? 1 : 0);
    }

    /** นับคน (หรือนับครั้งเมื่อ $unique=false) แยกช่องรวม/เพศ/กลุ่ม */
    private function count(Collection $pids, bool $unique = true): array
    {
        $out = array_fill_keys(self::COLS, 0);
        foreach (($unique ? $pids->unique() : $pids) as $pid) {
            $w = $this->who[$pid] ?? null;
            if (! $w) { continue; }
            $this->add($out, $w['sex'], $w['group']);
        }
        return $out;
    }

    /** คู่สัมผัส: นับรายคน เพศตามคู่สัมผัส กลุ่มตามผู้ป่วยที่ให้ข้อมูล */
    private function contactCount(Collection $contacts, Collection $visitPid): array
    {
        $out = array_fill_keys(self::COLS, 0);
        foreach ($contacts as $c) {
            $w = $this->who[$visitPid[$c->visit_id] ?? 0] ?? null;
            $this->add($out, (int) $c->cpsex, $w['group'] ?? 'other');
        }
        return $out;
    }

    private function add(array &$out, int $sex, string $group): void
    {
        $out['total']++;
        if ($sex === 1) { $out['m']++; } elseif ($sex === 2) { $out['f']++; }
        $out[$group]++;
    }
}
