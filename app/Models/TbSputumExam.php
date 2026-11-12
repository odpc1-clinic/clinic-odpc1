<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * ผลตรวจเสมหะรายเดือนของคอร์สการรักษาหนึ่งคอร์ส (TB 01 ข้อ 3)
 *
 * ผลเก็บเป็นข้อความ ไม่ใช่ตัวเลข เพราะของจริงในบัตรเขียนได้ทั้ง N, NG, 1+, 2+, "-"
 */
class TbSputumExam extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    /** มีคอลัมน์ Collect ครั้งที่ 2 หรือยัง (migration 2026_10_02_210000) — ก่อนรัน หน้าจอโชว์ Collect ช่องเดียวเหมือนเดิม */
    public static function hasCollect2(): bool
    {
        static $has = null;
        return $has ??= \Illuminate\Support\Facades\Schema::hasColumn('tb_sputum_exams', 'collect2');
    }

    /* ---------- ผูกกับส่วน "ส่งตรวจทางห้องปฏิบัติการ" (tb/_lab_fields) — 2569-10-05 ----------
     *
     * ผล AFB Spot / Collect 1 / Collect 2 (+ Molecular, Culture) ของคนไข้ที่มีบัตร TB 01 ที่ยังรักษาอยู่
     * ต้องเป็นค่าเดียวกับตารางผลเสมหะในบัตร — เดิมกรอกสองที่แล้วไม่ตรงกัน
     * ส่วนส่งตรวจจึงอ่าน/เขียนแถวในตารางนี้โดยตรง ส่วนใบคัดกรองเก็บสำเนาไว้ (ดูย้อนหลังหลังปิดบัตรได้)
     *
     * กติกาหาแถว (findForVisit) — ห้ามไปทับผลของการตรวจครั้งอื่น:
     *   1) id ที่หน้าจอส่งมา (แถวที่โชว์อยู่ตอนเปิด) ถ้ายังอยู่ในบัตรนี้
     *   2) แถวที่ "วันที่เก็บ" = วันที่ส่งเสมหะในข้อ 1 หรือวันที่ของ visit
     *   3) แถวของรอบเดียวกัน (ป้ายตรงกัน) ที่ยังไม่มีวันที่เก็บ
     *   ไม่เจอ = สร้างแถวใหม่ของรอบนั้นตอนบันทึก (ป้ายเดียวกันซ้ำได้ — ตารางรองรับตรวจซ้ำในเดือนเดียวกันอยู่แล้ว)
     * รอบ (roundFor) หาแบบเดียวกับการสั่งยา: รอบสั่งยาที่จ่ายวันเดียวกับ visit ก่อน ไม่มีค่อยใช้เดือนที่รักษา ณ วันนั้น
     */

    /** ความยาวคอลัมน์ในตารางนี้ — ข้อความยาวกว่านี้ (เช่น ผล DST) เก็บไว้ที่ใบคัดกรองอย่างเดียว ไม่ตัดทิ้งครึ่ง ๆ */
    public const LEN_AFB = 20;
    public const LEN_CULTURE = 20;
    public const LEN_MOLECULAR = 120;

    /** บัตร TB 01 ที่ยังรักษาอยู่ (ยังไม่มีผลการรักษา) ของคนไข้ใน visit นี้ — ไม่มี = เป็นคนไข้คัดกรอง */
    public static function activeRegFor(?Visit $visit): ?TbRegistration
    {
        if (! $visit || ! $visit->patient_id) { return null; }
        return TbRegistration::forVisit($visit);   // visit ย้อนหลัง = บัตรที่ครอบวันนั้น (2569-10-27)
    }

    /** รอบของวันที่ (พ.ศ.) หนึ่ง -> [ป้าย, เลขเดือน] */
    public static function roundFor(TbRegistration $reg, ?string $dateBe): array
    {
        $dateBe = TbRegistration::beDate($dateBe);
        if ($dateBe) {
            $dose = TbMonthlyDose::where('tb_registration_id', $reg->id)->orderBy('id')->get()
                ->filter(fn ($r) => TbRegistration::beDate($r->dispensed_date) === $dateBe)->last();
            if ($dose) {
                $label = trim((string) $dose->label);
                return [$label !== '' ? $label : (string) (int) $dose->month_no, (int) $dose->month_no];
            }
        }

        // เดือนที่รักษา ณ วันของ visit (ไม่ใช่วันนี้) — เปิดหน้าตรวจย้อนหลังจะได้รอบของวันนั้น
        $month = 0;
        $start = TbRegistration::beDate($reg->treatment_start_date);
        if ($start && $dateBe && $dateBe > $start) {
            try {
                $month = (int) \Carbon\Carbon::parse(\App\Utillity::BE2AD($start))
                    ->diffInMonths(\Carbon\Carbon::parse(\App\Utillity::BE2AD($dateBe)), true);
            } catch (\Throwable $e) {
                $month = 0;
            }
        }
        $month = max(0, min(31, $month));
        return [(string) $month, $month];
    }

    /** วันที่ส่งเสมหะจากข้อ 1 ของใบ (แถวเสมหะก่อน ไม่มีค่อยแถวแรกที่มีวันที่) — ไม่มี = null */
    public static function specimenDate(?TbScreening $sc): ?string
    {
        $specs = $sc ? (array) ($sc->lab_specimens ?? []) : [];
        $dated = array_values(array_filter($specs, fn ($r) => is_array($r) && ! empty($r['date'])));
        $sputum = array_values(array_filter($dated, fn ($r) => strpos((string) ($r['type'] ?? ''), 'sputum_') === 0));
        $pick = $sputum[0]['date'] ?? ($dated[0]['date'] ?? null);
        if (! $pick && $sc && $sc->lab_sent_date) { $pick = $sc->lab_sent_date; }
        return TbRegistration::beDate($pick);
    }

    /** แถวของ visit นี้ (ไม่สร้าง) — กติกาดูหัวข้อด้านบน */
    public static function findForVisit(TbRegistration $reg, Visit $visit, ?string $specDate, ?int $rowId = null): ?self
    {
        if ($rowId) {
            $row = self::where('tb_registration_id', $reg->id)->find($rowId);
            if ($row) { return $row; }
        }
        $rows  = self::where('tb_registration_id', $reg->id)->orderBy('sort')->orderBy('id')->get();
        $dates = array_values(array_filter([TbRegistration::beDate($specDate), TbRegistration::beDate($visit->date)]));

        $hit = $rows->first(fn ($r) => $r->collected_date && in_array(TbRegistration::beDate($r->collected_date), $dates, true));
        if ($hit) { return $hit; }

        [$label] = self::roundFor($reg, $visit->date);
        return $rows->first(fn ($r) => ! $r->collected_date
            && (trim((string) $r->label) !== '' ? trim((string) $r->label) : (string) $r->month_no) === $label);
    }

    /**
     * ค่าที่ส่วนส่งตรวจต้องโชว์ — มีบัตรที่ยังรักษา + เจอแถว = ค่าจากแถว (ทั้งแถว ไม่ปนกับใบคัดกรอง)
     * ไม่มีบัตร/ยังไม่มีแถว = ค่าจากใบคัดกรอง (ใบเก่าที่มีแต่ผล AFB ช่องเดียว = Spot)
     */
    public static function labValues(?TbScreening $sc, ?Visit $visit): array
    {
        $out = [
            'reg' => null, 'row' => null, 'round' => null,
            'spot'      => $sc->lab_afb_result ?? null,
            'collect1'  => TbScreening::hasAfbCollect() ? ($sc->lab_afb_collect1 ?? null) : null,
            'collect2'  => TbScreening::hasAfbCollect() ? ($sc->lab_afb_collect2 ?? null) : null,
            'molecular' => $sc->lab_molecular_result ?? null,
            'culture'   => $sc->lab_culture_result ?? null,
        ];
        $reg = self::activeRegFor($visit);
        if (! $reg) { return $out; }

        $out['reg']   = $reg;
        $out['round'] = self::roundFor($reg, $visit->date)[0];
        $row = self::findForVisit($reg, $visit, self::specimenDate($sc));
        if (! $row) { return $out; }

        $out['row']      = $row;
        $out['round']    = trim((string) $row->label) !== '' ? (string) $row->label : (string) $row->month_no;
        $out['spot']     = $row->spot;
        $out['collect1'] = $row->collect;
        $out['collect2'] = self::hasCollect2() ? $row->collect2 : null;
        // ข้อความยาวที่ตารางเก็บได้แค่ส่วนต้น — ถ้าใบคัดกรองมีฉบับเต็มที่ขึ้นต้นตรงกัน โชว์ฉบับเต็ม
        $full = fn ($rowV, $scV) => ($rowV !== null && $scV !== null && mb_strlen((string) $scV) > mb_strlen((string) $rowV)
                                     && mb_substr((string) $scV, 0, mb_strlen((string) $rowV)) === (string) $rowV) ? $scV : $rowV;
        $out['molecular'] = $full($row->molecular, $sc->lab_molecular_result ?? null);
        // Culture ในตารางเป็นตัวเลือกสั้น (Pending/NG/Growth) — ผลยาว (DST) อยู่ที่ใบคัดกรองอย่างเดียว ตารางว่างจึงโชว์ของใบ
        $out['culture'] = $row->culture !== null && $row->culture !== '' ? $row->culture : ($sc->lab_culture_result ?? null);
        return $out;
    }

    /**
     * บันทึกผลจากส่วนส่งตรวจลงแถวของรอบนี้ — เรียกจาก TbScreening::saveLabSection หลังบันทึกใบแล้ว
     * เขียนเฉพาะช่องที่ส่งมา (ช่องที่หน้าจอปิดไว้/ไม่ได้ติ๊กวิธีนั้น = ไม่แตะค่าในตาราง ที่อาจกรอกมาจากบัตร)
     */
    public static function syncFromLab(Visit $visit, TbScreening $sc, array $in): ?self
    {
        if (! array_key_exists('lab_sec2_on', $in)) { return null; }
        $reg = self::activeRegFor($visit);
        if (! $reg) { return null; }

        $text = fn ($k) => trim((string) ($in[$k] ?? ''));
        $spec = self::specimenDate($sc);
        $row  = self::findForVisit($reg, $visit, $spec, (int) ($in['sputum_row_id'] ?? 0) ?: null);

        $vals = [];
        $afbCols = ['lab_afb_result' => 'spot', 'lab_afb_collect1' => 'collect'];
        if (self::hasCollect2()) { $afbCols['lab_afb_collect2'] = 'collect2'; }
        foreach ($afbCols as $k => $col) {
            if (! array_key_exists($k, $in)) { continue; }
            $v = $text($k);
            if ($v === '') { $vals[$col] = null; continue; }
            // ค่าเก่าในตารางที่ไม่อยู่ในตัวเลือก (เช่น NG, -) หน้าจอโชว์เป็นตัวเลือกพิเศษ — ส่งกลับมาเหมือนเดิม = คงไว้
            if (isset(TbScreening::$afbResultOption[$v]) || ($row && (string) $row->$col === $v)) {
                $vals[$col] = mb_substr($v, 0, self::LEN_AFB);
            }
        }
        if (array_key_exists('lab_molecular_result', $in)) {
            $v = $text('lab_molecular_result');
            $vals['molecular'] = $v === '' ? null : mb_substr($v, 0, self::LEN_MOLECULAR);
        }
        if (array_key_exists('lab_culture_result', $in)) {
            $v = $text('lab_culture_result');
            if ($v === '') { $vals['culture'] = null; }
            elseif (mb_strlen($v) <= self::LEN_CULTURE) { $vals['culture'] = $v; }
            // ยาวกว่าช่องในตาราง = ผลความไวต่อยาแบบเต็ม เก็บที่ใบคัดกรอง ไม่แตะช่องในตาราง
        }
        if (! $vals) { return $row; }

        if (! $row) {
            // ไม่สร้างแถวเปล่า — ล้างช่องว่างทั้งหมดในรอบที่ยังไม่มีแถว = ไม่มีอะไรต้องเก็บ
            if (! array_filter($vals, fn ($v) => $v !== null)) { return null; }
            [$label, $month] = self::roundFor($reg, $visit->date);
            return self::create($vals + [
                'tb_registration_id' => $reg->id,
                'label'          => $label,
                'month_no'       => $month,
                'sort'           => (int) self::where('tb_registration_id', $reg->id)->max('sort') + 1,
                'collected_date' => $spec ?: TbRegistration::beDate($visit->date),
            ]);
        }

        // วันที่เก็บ = วันที่ส่งเสมหะในข้อ 1 · ไม่ได้ระบุวันส่ง = เติมวันของ visit เฉพาะแถวที่ยังว่าง
        if ($spec && TbRegistration::beDate($row->collected_date) !== $spec) { $vals['collected_date'] = $spec; }
        elseif (! $row->collected_date) { $vals['collected_date'] = TbRegistration::beDate($visit->date); }

        $row->update($vals);
        return $row;
    }

    /**
     * เปิดบัตร TB 01 ใหม่ — ยกผลเสมหะจากใบคัดกรอง (ตอนวินิจฉัย) ลงรอบ 0 ถ้ารอบ 0 ยังว่าง
     * พยาบาลจะได้ไม่ต้องกรอกผลเดิมซ้ำในตาราง และส่วนส่งตรวจของ visit นั้นจะเจอแถวนี้ (วันที่เก็บตรงกัน)
     */
    /**
     * ใบคัดกรองที่จะยกผลลงรอบ 0 — เปิดบัตรจากปุ่มในใบ = ใบนั้น
     * เปิดจากหน้าบัตรเอง = ใบล่าสุดของคนไข้ที่มีผลเสมหะ ภายใน 6 เดือน (ใบเก่ากว่านั้นเป็นของคอร์สก่อน ห้ามยกมา)
     */
    public static function screeningForSeed(TbRegistration $reg, ?int $fromVisitId): ?TbScreening
    {
        $q = TbScreening::with('visit')->where('patient_id', $reg->patient_id);
        if ($fromVisitId) {
            return $q->where('visit_id', $fromVisitId)->first();
        }
        // visits.date เป็น พ.ศ. แบบข้อความ — เทียบแบบข้อความได้ (CLAUDE.md ข้อ 4)
        $since = (date('Y', strtotime('-6 months')) + 543) . date('-m-d', strtotime('-6 months'));
        return $q->where(fn ($w) => $w->whereNotNull('lab_afb_result')->orWhereNotNull('lab_molecular_result'))
            ->whereHas('visit', fn ($v) => $v->where('date', '>=', $since))
            ->orderByDesc('id')->first();
    }

    public static function seedRoundZero(TbRegistration $reg, ?TbScreening $sc): ?self
    {
        if (! $sc) { return null; }
        $hasZero = self::where('tb_registration_id', $reg->id)->where('month_no', 0)->get()
            ->contains(fn ($r) => collect($r->only(['spot', 'collect', 'collect2', 'molecular', 'culture', 'collected_date']))
                ->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty());
        if ($hasZero) { return null; }

        $mol = trim((string) $sc->lab_molecular_result);
        $cul = trim((string) $sc->lab_culture_result);
        $vals = [
            'spot'      => $sc->lab_afb_result ?: null,
            'collect'   => TbScreening::hasAfbCollect() ? ($sc->lab_afb_collect1 ?: null) : null,
            'molecular' => $mol !== '' ? mb_substr($mol, 0, self::LEN_MOLECULAR) : null,
            'culture'   => $cul !== '' && mb_strlen($cul) <= self::LEN_CULTURE ? $cul : null,
        ];
        if (self::hasCollect2()) { $vals['collect2'] = TbScreening::hasAfbCollect() ? ($sc->lab_afb_collect2 ?: null) : null; }
        if (! array_filter($vals, fn ($v) => $v !== null)) { return null; }

        $visit = $sc->visit;
        $isSputum = collect((array) ($sc->lab_specimens ?? []))->contains(fn ($r) => strpos((string) ($r['type'] ?? ''), 'sputum_') === 0);
        return self::create($vals + [
            'tb_registration_id' => $reg->id,
            'label' => '0', 'month_no' => 0, 'sort' => 0,
            'collected_date' => self::specimenDate($sc) ?: TbRegistration::beDate(optional($visit)->date),
            'specimen' => $isSputum ? 'เสมหะ' : null,
        ]);
    }

    /** ลักษณะเสมหะตามใบรายงานของห้องแล็บ (กระดาษสีเหลือง) */
    public static $appearanceOption = [
        1 => '1 ข้น เหลือง เขียว',
        2 => '2 ปนเลือด',
        3 => '3 ปนน้ำลาย',
        4 => '4 น้ำลาย',
    ];

    /** ชนิดสิ่งส่งตรวจ — ค่าปกติคือเสมหะ ที่เหลือเป็นเคสวัณโรคนอกปอด */
    public static $specimenOption = [
        'เสมหะ', 'เลือด', 'ปัสสาวะ', 'ชิ้นเนื้อ (tissue)', 'ต่อมน้ำเหลือง (LN)', 'Bronchial lavage', 'อื่นๆ',
    ];

    /**
     * ผล Molecular ที่ใช้บ่อย — เป็นตัวช่วยพิมพ์ (datalist) ไม่ใช่ตัวเลือกบังคับ ยังพิมพ์อิสระได้
     * ที่เดียวที่เก็บรายการนี้ ตารางเสมหะและช่องผล Molecular ในใบสั่งตรวจดึงจากตรงนี้ทั้งคู่
     * LPA/PCR เดิมเป็นแค่ "LPA: " ให้พิมพ์ต่อเอง คลินิกขอให้มีผลสำเร็จรูปเหมือน Xpert (2569-10-02)
     */
    public static $molecularOption = [
        'Xpert: MTB not detected',
        'Xpert: MTB detected, RIF resistance not detected',
        'Xpert: MTB detected, RIF resistance detected',
        'LPA: MTB not detected',
        'LPA: MTB detected',
        'PCR: MTB not detected',
        'PCR: MTB detected',
    ];

    /**
     * ผลเพาะเชื้อ — เก็บเป็นข้อความตามที่เลือก (ไม่ใช่รหัส) ค่าเก่าที่พิมพ์อิสระไว้จึงยังอ่านได้ตรง ๆ
     * "N/A" ไม่อยู่ในนี้ เพราะเป็นค่าว่าง (ยังไม่ส่งเพาะเชื้อ) ถ้าเก็บเป็นข้อความทุกแถวจะกลายเป็นแถวที่มีข้อมูล
     */
    public static $cultureOption = ['Pending', 'NG', 'Growth'];

    public function registration()
    {
        return $this->belongsTo(TbRegistration::class, 'tb_registration_id');
    }

    public function auditLabel(): string
    {
        return 'ผลเสมหะเดือนที่ ' . $this->month_no;
    }
}
