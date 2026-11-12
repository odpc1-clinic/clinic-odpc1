<?php

namespace App\Services;

use App\Models\TbRegistration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * รายงานประจำเดือนคลินิกวัณโรค — 2 ตารางตามแบบฟอร์มที่คลินิกส่ง (2569-09-30)
 *
 *   ตารางบน (ESM-Estimate): นับวันที่ 21 ของเดือนก่อน ถึงวันที่ 20 ของเดือนที่เลือก
 *   ตารางล่าง (รายงานผลการปฏิบัติงาน): นับทั้งเดือนที่เลือก
 *
 * นิยามที่คลินิกยืนยัน:
 *   - ผู้ป่วยใหม่ = มาคลินิกวัณโรค (visits.clinic = 2) ครั้งแรกในช่วงนั้น · เก่า = เคยมาก่อนช่วงนั้น
 *   - ต่างชาติ = สัญชาติไม่ใช่ไทยทั้งหมด (สัญชาติว่างก็นับเป็นต่างชาติ — หน้าจอบอกจำนวนไว้ให้ตรวจ)
 *   - บรรทัดที่ระบบไม่มีข้อมูล (contact นอก/ใน, R/O TB, Refer ฯลฯ) ไม่มีใครกรอก — แสดงเป็น "—"
 *
 * ทุกตัวเลขแยก ไทย / ต่างชาติ / รวม และนับเป็น "ราย" (คนไม่ซ้ำ) ยกเว้นบรรทัดที่หน่วยเป็น "ครั้ง"
 * วันที่ทั้งหมดเป็น พ.ศ. Y-m-d เทียบแบบข้อความได้ (คลินิกวัณโรคไม่มีแถวยุค ค.ศ.)
 */
class TbMonthlyReport
{
    private const THAI = ['ไทย', 'tha', 'th', 'thai', 'thailand'];

    /** กลุ่มระยะ DOT สำหรับข้อ 7 — ตามที่คลินิกกำหนด 2569-09-30 */
    public const DOT_GROUPS = [
        'active'   => ['label' => 'ระยะเข้มข้น (Active phase)',               'reasons' => ['Active phase']],
        'resist'   => ['label' => 'ดื้อยา (MDR, Pre-XDR, XDR)',                'reasons' => ['MDR', 'Pre-XDR', 'XDR']],
        'ltbi'     => ['label' => 'วัณโรคแฝง (LTBI/TPT)',                      'reasons' => ['LTBI/TPT', 'LTBI', 'TPT']],
        'hard'     => ['label' => 'เคสรักษายาก',                                'reasons' => ['เคสรักษายาก']],
        'default'  => ['label' => 'เฝ้าระวังการขาดยา',                          'reasons' => ['เฝ้าระวังการขาดยา']],
        'side'     => ['label' => 'เฝ้าระวังอาการข้างเคียง',                    'reasons' => ['เฝ้าระวังอาการข้างเคียง']],
    ];

    /** @var Collection patient_id => bool (ไทยหรือไม่) */
    private $thai;

    public function build(string $ym): array
    {
        [$y, $m] = array_map('intval', explode('-', $ym));
        $monthFrom = sprintf('%04d-%02d-01', $y, $m);
        // วันสุดท้ายจริงของเดือน — คอลัมน์วันที่เป็นชนิด date ถ้าส่ง "-31" ของเดือนที่มี 30 วัน MySQL เทียบไม่ได้เลย (ได้ 0 ทั้งตาราง)
        // ปีอธิกสุรทินต้องคิดจาก ค.ศ.
        $monthTo   = sprintf('%04d-%02d-%02d', $y, $m, (int) date('t', mktime(0, 0, 0, $m, 1, $y - 543)));
        [$py, $pm] = $m === 1 ? [$y - 1, 12] : [$y, $m - 1];
        $esmFrom   = sprintf('%04d-%02d-21', $py, $pm);
        $esmTo     = sprintf('%04d-%02d-20', $y, $m);

        // วันแรกที่มาคลินิกวัณโรคของทุกคน (ใช้แยกใหม่/เก่า)
        $first = DB::table('visits')->where('clinic', 2)
            ->groupBy('patient_id')->selectRaw('patient_id, MIN(date) AS first_date')
            ->pluck('first_date', 'patient_id');
        // เคสเก่าจากทะเบียนกระดาษ (ติ๊กที่หน้าตรวจ 2569-11-05) — เคยมาก่อนใช้ระบบ นับเป็นผู้ป่วยเก่าเสมอ
        if (\App\Models\Patient::tbPaperOldReady()) {
            foreach (DB::table('patients')->whereIn('id', $first->keys())->where('tb_paper_old', 1)->pluck('id') as $pid) {
                $first[$pid] = '0000-00-00';
            }
        }

        $this->thai = DB::table('patients')->whereIn('id', $first->keys())->pluck('nationality', 'id')
            ->map(fn ($n) => in_array(mb_strtolower(trim((string) $n)), self::THAI, true));

        return [
            'esm'   => ['from' => $esmFrom, 'to' => $esmTo, 'rows' => $this->esmRows($esmFrom, $esmTo, $first)],
            'month' => ['from' => $monthFrom, 'to' => $monthTo, 'rows' => $this->monthRows($monthFrom, $monthTo, $first)],
            'unknownNationality' => DB::table('patients')->whereIn('id', $first->keys())
                ->where(fn ($q) => $q->whereNull('nationality')->orWhere('nationality', '')->orWhere('nationality', 'N/A'))->count(),
        ];
    }

    /* ======================================================== ตารางบน (21 - 20) */

    private function esmRows(string $from, string $to, Collection $first): array
    {
        $visits  = $this->visits($from, $to);
        $newIds  = $visits->pluck('patient_id')->unique()->filter(fn ($p) => ($first[$p] ?? '') >= $from);
        $oldIds  = $visits->pluck('patient_id')->unique()->diff($newIds);
        $oldVis  = $visits->whereIn('patient_id', $oldIds->all());

        $sc      = $this->screenings($from, $to)->whereIn('patient_id', $newIds->all());
        $regs    = $this->registeredIn($from, $to)->whereIn('patient_id', $newIds->all());
        $imp     = fn (array $codes) => $sc->whereIn('impression', $codes)->pluck('patient_id');

        return [
            $this->head('1. ทำบัตร/ทะเบียน'),
            $this->row('1.1 ให้บริการผู้ป่วยใหม่', 'ราย', $newIds, true),
            $this->row('1.2 ให้บริการผู้ป่วยเก่า (คัดประวัติ)', 'ราย', $oldIds),
            $this->row('1.3 ให้บริการผู้ป่วยเก่า (คัดประวัติ)', 'ครั้ง', $oldVis->pluck('patient_id'), false, true),
            $this->head('ชนิดผู้ป่วยใหม่ที่มารับบริการ (เฉพาะรายใหม่ ข้อ 1.1)'),
            // มาด้วยเรื่อง (ใบคัดกรอง — 2569-11-04) · ใบเก่าที่ยังไม่เลือก นับจากช่องผู้สัมผัสแบบเดิม
            $this->row('1.1.1 ผู้มีอาการสงสัยวัณโรค', 'ราย', $sc->where('come', 1)->pluck('patient_id')),
            $this->row('1.1.2 ตรวจผู้สัมผัสวัณโรค', 'ราย', $sc->where('come', 2)->pluck('patient_id')),
            // contact ใน = ผู้ป่วยต้นทางรักษาที่คลินิกเรา (ผูกบัตรต้นทางไว้ในใบคัดกรอง) · ไม่ได้ผูก = contact นอก
            $this->row('1) contact นอก', 'ราย', $sc->where('come', 2)->whereNull('index_registration_id')->pluck('patient_id')),
            $this->row('2) contact ใน (index รักษา ศวช.)', 'ราย', $sc->where('come', 2)->whereNotNull('index_registration_id')->pluck('patient_id')),
            ...(\App\Models\TbScreening::comeReasonReady() ? [
                $this->row('1.1.3 รับส่งต่อมาจากสถานพยาบาลอื่น', 'ราย', $sc->where('come', 3)->pluck('patient_id')),
                $this->row('1.1.4 มาขอคำปรึกษา', 'ราย', $sc->where('come', 4)->pluck('patient_id')),
                $this->row('1.1.5 leprosy', 'ราย', $sc->where('come', 5)->pluck('patient_id')),
            ] : [
                $this->na('1.1.3 รับส่งต่อมาจากสถานพยาบาลอื่น'),
                $this->na('1.1.4 มาขอคำปรึกษา'),
                $this->na('1.1.5 leprosy'),
            ]),
            $this->head('2. การตรวจวินิจฉัย/รักษาผู้ป่วยเฉพาะ (รายใหม่ ข้อ 1.1)'),
            $this->row('2.1 ผู้ป่วยวัณโรค (TB)', 'ราย',
                $imp([3, 4, 5])->merge($regs->where('registry_type', TbRegistration::TYPE_TB)->pluck('patient_id'))),
            // Impression 7 / 8 / 9 เพิ่ม 2569-11-04
            $this->row('2.2 ผู้ป่วยสงสัยวัณโรค รอผลเสมหะ (R/O TB)', 'ราย', $imp([7])),
            $this->row('2.3 ผู้ป่วยโรคทรวงอกอื่นๆ (Non-TB)', 'ราย', $imp([8])),
            $this->row('2.4 ผู้ป่วยปอดปกติ (Normal)', 'ราย', $imp([1])),
            $this->row('2.5 ผู้ป่วยวัณโรคดื้อยา (MDR TB)', 'ราย', $regs->where('registry_type', TbRegistration::TYPE_MDR)->pluck('patient_id')),
            $this->row('2.6 ผู้ป่วยวัณโรคระยะสงบ (Inactive-TB)', 'ราย', $imp([9])),
            // ช่อง Refer ในหน้าตรวจ (visits.refer_hospital) ของใบคัดกรองผู้ป่วยใหม่ในช่วงนี้
            $this->row('2.7 ไปตรวจ รพ.อื่น (refer) ทุกกรณี', 'ราย', $sc->where('referred', true)->pluck('patient_id')),
            $this->row('2.8 LTBI', 'ราย', $imp([2])->merge($regs->where('registry_type', TbRegistration::TYPE_LTBI)->pluck('patient_id'))),
            $this->row('2.9 อื่นๆ (Other)', 'ราย', $imp([6])),
            $this->na('2.10 ให้คำแนะนำ/มีอาการอื่นๆและแนะนำให้ไปตรวจ รพ.อื่น'),
        ];
    }

    /* ======================================================== ตารางล่าง (ทั้งเดือน) */

    private function monthRows(string $from, string $to, Collection $first): array
    {
        $visits = $this->visits($from, $to);
        $newIds = $visits->pluck('patient_id')->unique()->filter(fn ($p) => ($first[$p] ?? '') >= $from);
        $oldIds = $visits->pluck('patient_id')->unique()->diff($newIds);

        $sc   = $this->screenings($from, $to);
        $regs = $this->registeredIn($from, $to);
        // บัตรที่ยกเลิกเพราะเปิดซ้ำ ไม่ใช่การรักษาจริง — ไม่นับทั้งใบ
        $all  = DB::table('tb_registrations')->where(fn ($q) => $q->whereNull('outcome')->orWhere('outcome', '!=', TbRegistration::OUTCOME_VOID))
            ->get(['id', 'patient_id', 'registry_type', 'register_date', 'register_type', 'outcome', 'outcome_date']);

        // อยู่ระหว่างรักษา ณ สิ้นเดือน: ขึ้นทะเบียนแล้ว และยังไม่จำหน่าย (หรือจำหน่ายหลังสิ้นเดือน)
        $onTx = $all->filter(fn ($r) => $r->register_date && $r->register_date <= $to
            && (! $r->outcome || ($r->outcome_date && $r->outcome_date > $to)));
        $out  = $all->filter(fn ($r) => $r->outcome && $r->outcome_date >= $from && $r->outcome_date <= $to);

        $newTbRegs = $regs->whereIn('registry_type', [TbRegistration::TYPE_TB, TbRegistration::TYPE_MDR]);
        $hasLtbi = $this->registeredBy($to, [TbRegistration::TYPE_LTBI]);
        $hasTb   = $this->registeredBy($to, [TbRegistration::TYPE_TB, TbRegistration::TYPE_MDR]);
        $ltbiNoReg = $sc->where('impression', 2)->reject(fn ($r) => isset($hasLtbi[$r->patient_id]));
        $tbNoReg   = $sc->whereIn('impression', [3, 4, 5])->reject(fn ($r) => isset($hasTb[$r->patient_id]));

        $rows = [
            ...$this->group('1. จำนวนผู้ป่วยลงทะเบียนเข้ารับบริการ', [
                $this->row('1.1 ผู้รับบริการรายใหม่', 'ราย', $newIds),
                $this->row('1.2 ผู้รับบริการรายเก่า', 'ราย', $oldIds),
            ]),
            ...$this->group('2. การคัดกรองวัณโรค', [
                $this->row('2.1 คัดกรองผู้ป่วยสงสัยวัณโรค', 'ราย', $sc->where('come', 1)->pluck('patient_id')),
                $this->row('2.2 คัดกรองผู้สัมผัสวัณโรคปอด', 'ราย', $sc->where('come', 2)->pluck('patient_id')),
            ]),
            ...$this->group('3. พบผู้ติดเชื้อวัณโรคระยะแฝง', [
                $this->row('3.1 ขึ้นทะเบียนรักษาวัณโรคระยะแฝง', 'ราย', $regs->where('registry_type', TbRegistration::TYPE_LTBI)->pluck('patient_id')),
                // Impression LTBI ในเดือนนี้ ที่ยังไม่มีบัตร LTBI: กดส่งต่อไว้ = refer · ยังไม่ส่ง = รอขึ้นทะเบียน (2569-11-04)
                $this->row('3.2 Refer ไปรักษา รพ.อื่น', 'ราย', $ltbiNoReg->where('referred', true)->pluck('patient_id')),
                $this->row('3.3 อยู่ระหว่างรอขึ้นทะเบียน', 'ราย', $ltbiNoReg->where('referred', false)->pluck('patient_id')),
            ]),
            ...$this->group('4. จำนวนผู้ป่วยวัณโรครายใหม่', [
                $this->row('4.1 ขึ้นทะเบียนรักษาวัณโรครายใหม่', 'ราย', $newTbRegs->where('register_type', '!=', 5)->pluck('patient_id')),
                // Impression TB ในเดือนนี้ ที่กด Refer และไม่ได้ขึ้นทะเบียนรักษาที่นี่
                $this->row('4.2 Refer ไปรักษา รพ.อื่น', 'ราย', $tbNoReg->where('referred', true)->pluck('patient_id')),
                $this->row('4.3 รับ Refer มารักษาต่อเนื่อง', 'ราย', $newTbRegs->where('register_type', 5)->pluck('patient_id')),
            ]),
            // วัณโรคแฝงที่ยังรักษาอยู่ — ชีทข้อ 5.4 (เพิ่ม 2569-11-04) · ยอดหัวข้อ 5 นับเฉพาะวัณโรค (DS + MDR) ตามชีท
            ...$this->group('5. ผู้ป่วยวัณโรคอยู่ระหว่างรักษา', [
                $this->row('5.1 วัณโรคไม่ดื้อยา (DS-TB)', 'ราย', $onTx->where('registry_type', TbRegistration::TYPE_TB)->pluck('patient_id')),
                $this->row('5.2 วัณโรคดื้อยา (MDR-TB)', 'ราย', $onTx->where('registry_type', TbRegistration::TYPE_MDR)->pluck('patient_id')),
                $this->row('5.4 ผู้ที่รับการรักษาวัณโรคแฝง', 'ราย', $onTx->where('registry_type', TbRegistration::TYPE_LTBI)->pluck('patient_id')),
            ], $onTx->whereIn('registry_type', [TbRegistration::TYPE_TB, TbRegistration::TYPE_MDR])->pluck('patient_id')),
            ...$this->group('6. ผู้ป่วยที่จำหน่ายจากการรักษา', [
                $this->row('6.1 ผู้ป่วย DS TB', 'ราย', $out->where('registry_type', TbRegistration::TYPE_TB)->pluck('patient_id')),
                $this->row('6.2 ผู้ป่วย MDR TB', 'ราย', $out->where('registry_type', TbRegistration::TYPE_MDR)->pluck('patient_id')),
                $this->row('6.3 ผู้ป่วย TPT', 'ราย', $out->where('registry_type', TbRegistration::TYPE_LTBI)->pluck('patient_id')),
            ]),
        ];

        // 7. DOT — คนไข้ที่มีวันลงกินยาในเดือนนี้ แยกตามระยะที่ประทับไว้ ณ วันนั้น
        //    คนเดียวเปลี่ยนระยะกลางเดือน = นับในทั้งสองกลุ่ม หัวข้อ 7 จึงนับคนไม่ซ้ำ ไม่ใช่ผลรวม
        $dot = DB::table('tb_dot_days as d')->join('tb_registrations as r', 'r.id', '=', 'd.tb_registration_id')
            ->whereNotNull('d.mark')
            // แถวที่หลุดเข้ามาเป็นปี ค.ศ. (เคยเจอ 1 แถว) ก็นับด้วย ไม่งั้นตกหล่นเงียบ ๆ (รีวิว 2569-09-30)
            ->where(fn ($q) => $q->whereBetween('d.dot_date', [$from, $to])
                ->orWhereBetween('d.dot_date', [self::beToAd($from), self::beToAd($to)]))
            ->select('r.patient_id', 'r.registry_type', 'r.dot_reason as card_reason', 'd.dot_reason', 'd.phase')->get();

        // จัดกลุ่มแต่ละวัน — เดิมดูแค่เหตุผลที่ประทับไว้ในวันนั้น วันที่ติ๊กก่อนกรอกเหตุผลที่บัตรจึงว่าง
        // แล้วตกไป "อื่นๆ" (เช่น LTBI ต.ค. 69 ขึ้น 7.3 แค่ 2 คน · TB ขอ 2569-11-10)
        // ลำดับ: เหตุผลของวันนั้น → เหตุผลที่บัตรตอนนี้ → ชนิดบัตร (LTBI = 7.3, MDR = 7.2) → บัตร TB ระยะเข้มข้นวันนั้น = 7.1
        // เหตุผลที่พยาบาลเลือกไว้ชนะชนิดบัตร คนเดียวจึงไม่เด้งขึ้นสองกลุ่มในวันเดียวกัน
        $reasonGroup = [];
        foreach (self::DOT_GROUPS as $k => $g) {
            foreach ($g['reasons'] as $r) { $reasonGroup[mb_strtolower($r)] = $k; }
        }
        $groupsOf = function ($d) use ($reasonGroup) {
            $reason = trim((string) $d->dot_reason) ?: trim((string) $d->card_reason);
            if ($g = $reasonGroup[mb_strtolower($reason)] ?? null) { return [$g]; }
            $type = (int) $d->registry_type;
            if ($type === TbRegistration::TYPE_LTBI) { return ['ltbi']; }
            if ($type === TbRegistration::TYPE_MDR)  { return ['resist']; }
            return (int) $d->phase === 1 ? ['active'] : [];
        };
        $dot = $dot->map(function ($d) use ($groupsOf) { $d->groups = $groupsOf($d); return $d; });

        $children = [];
        foreach (self::DOT_GROUPS as $k => $g) {
            $children[] = $this->row('7.' . (count($children) + 1) . ' ' . $g['label'], 'ราย',
                $dot->filter(fn ($d) => in_array($k, $d->groups, true))->pluck('patient_id'));
        }
        $other = $dot->filter(fn ($d) => ! $d->groups)->pluck('patient_id');
        if ($other->isNotEmpty()) {
            $children[] = $this->row('7.' . (count($children) + 1) . ' ไม่ระบุระยะ / อื่นๆ', 'ราย', $other);
        }
        $rows = array_merge($rows, $this->group('7. จำนวนผู้ป่วยเข้ารับการกำกับการกินยา (DOT)', $children, $dot->pluck('patient_id')));

        return $rows;
    }

    /* ======================================================== ข้อมูลดิบ */

    private function visits(string $from, string $to): Collection
    {
        return DB::table('visits')->where('clinic', 2)->whereBetween('date', [$from, $to])->get(['id', 'patient_id', 'date']);
    }

    /** ใบคัดกรองที่ทำในช่วงนั้น (ตามวันที่ของ visit) */
    private function screenings(string $from, string $to): Collection
    {
        $cols = ['s.patient_id', 's.contact_tb', 's.impression', 's.index_registration_id'];
        if (\App\Models\TbScreening::comeReasonReady()) { $cols[] = 's.come_reason'; }
        if (\App\Models\Visit::referReady()) { $cols[] = 'v.refer_hospital'; }
        return DB::table('tb_screenings as s')->join('visits as v', 'v.id', '=', 's.visit_id')
            ->whereBetween('v.date', [$from, $to])
            ->get($cols)
            ->map(function ($r) {
                $r->contact_tb = $r->contact_tb === null ? null : (int) $r->contact_tb;
                $r->impression = $r->impression === null ? null : (int) $r->impression;
                $r->come_reason = isset($r->come_reason) ? (int) $r->come_reason : null;
                // มาด้วยเรื่อง: ใบที่ยังไม่เลือก (ใบเก่า) เดาจากช่องผู้สัมผัสแบบเดิม — ผู้สัมผัส = 2 ไม่ใช่ = 1 (มีอาการสงสัย)
                $r->come = $r->come_reason ?: ($r->contact_tb === 1 ? 2 : 1);
                $r->referred = trim((string) ($r->refer_hospital ?? '')) !== '';
                return $r;
            });
    }

    /** คนไข้ที่ขึ้นทะเบียนประเภทนั้นไว้แล้ว (เมื่อไหร่ก็ได้ จนถึงวันสุดท้ายของช่วง) — ไว้ตัดออกจากแถว refer / รอขึ้นทะเบียน */
    private function registeredBy(string $to, array $types): array
    {
        return DB::table('tb_registrations')->whereIn('registry_type', $types)->where('register_date', '<=', $to)
            ->where(fn ($q) => $q->whereNull('outcome')->orWhere('outcome', '!=', TbRegistration::OUTCOME_VOID))
            ->pluck('patient_id')->flip()->all();
    }

    private function registeredIn(string $from, string $to): Collection
    {
        return DB::table('tb_registrations')->whereBetween('register_date', [$from, $to])
            ->where(fn ($q) => $q->whereNull('outcome')->orWhere('outcome', '!=', TbRegistration::OUTCOME_VOID))   // บัตรซ้ำไม่นับ
            ->get(['id', 'patient_id', 'registry_type', 'register_type'])
            ->map(function ($r) {
                $r->registry_type = (int) $r->registry_type;
                $r->register_type = (int) $r->register_type;
                return $r;
            });
    }

    /* ======================================================== แถว */

    /** นับ ไทย/ต่างชาติ/รวม — $visitsCount = นับทุกแถว (หน่วยครั้ง) ไม่ใช่คนไม่ซ้ำ */
    private function row(string $label, string $unit, Collection $patientIds, bool $strong = false, bool $visitsCount = false): array
    {
        $ids = $visitsCount ? $patientIds->values() : $patientIds->filter()->unique()->values();
        $thai = $ids->filter(fn ($p) => $this->isThai($p))->count();

        return ['type' => 'row', 'label' => $label, 'unit' => $unit, 'strong' => $strong,
                'thai' => $thai, 'foreign' => $ids->count() - $thai, 'total' => $ids->count(),
                'patients' => $patientIds->filter()->unique()->values()->all()];
    }

    /** หัวข้อที่มีตัวเลขรวมของลูก (คนไม่ซ้ำ) — $ids ส่งมาเองได้เมื่อผลรวมลูกนับคนซ้ำ */
    private function group(string $label, array $children, ?Collection $ids = null): array
    {
        $ids = $ids ?? collect($children)->where('type', 'row')->pluck('patients')->flatten();
        $head = $this->row($label, 'ราย', $ids, true);
        $head['type'] = 'group';

        return array_merge([$head], $children);
    }

    private function head(string $label): array
    {
        return ['type' => 'head', 'label' => $label];
    }

    /** บรรทัดที่ระบบไม่มีข้อมูลให้นับ */
    private function na(string $label): array
    {
        return ['type' => 'na', 'label' => $label, 'unit' => 'ราย'];
    }

    private function isThai($patientId): bool
    {
        if (! $this->thai->has($patientId)) {
            $n = DB::table('patients')->where('id', $patientId)->value('nationality');
            $this->thai[$patientId] = in_array(mb_strtolower(trim((string) $n)), self::THAI, true);
        }
        return (bool) $this->thai[$patientId];
    }

    /** "2569-09-21" -> "2026-09-21" */
    private static function beToAd(string $d): string
    {
        return ((int) substr($d, 0, 4) - 543) . substr($d, 4);
    }
}
