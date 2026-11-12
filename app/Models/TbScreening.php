<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * ใบซักประวัติ/คัดกรองวัณโรคของการมาหนึ่งครั้ง
 *
 * คนละระดับกับ TbRegistration: ใบนี้ผูกกับ visit (มากี่ครั้งก็มีกี่ใบ)
 * ส่วนบัตร TB 01 ผูกกับคอร์สการรักษา (เปิดครั้งเดียวใช้ยาวเป็นปี)
 */
class TbScreening extends Model
{
    use Auditable;
    use \App\Models\Concerns\HasIcd10Codes;

    /** ชื่อเจ้าของใน icd10_entries — ต้องตรงกับที่ controller ส่งให้ Icd10Code::syncFor() */
    protected static $icd10Owner = 'tb_screening';

    protected $guarded = ['id'];

    protected $casts = [
        'labs_ordered'       => 'array',
        'lab_methods'        => 'array',
        'lab_specimens'      => 'array',   // [{type, other, date}] ส่งได้หลายสิ่งส่งตรวจ (2569-09-29)
        'lab_molecular'      => 'array',
        'lung_marks'         => 'array',   // จุด/เส้นที่มาร์คบนรูปปอด (การตรวจร่างกาย)
        'cxr_results'        => 'array',   // [{view, result, note}] ผลเอกซเรย์หลายท่าในวันเดียว (2569-10-02)
        'has_arv'            => 'boolean',
        'ct_same_house'      => 'boolean',
        'ct_same_room'       => 'boolean',
        'ct_aircon'          => 'boolean',
        'ct_close'           => 'boolean',
        'ct_close_8h'        => 'boolean',
        'ct_close_120h'      => 'boolean',
        'ct_low_risk'        => 'boolean',
        'has_dm'             => 'boolean',
        'sym_cough_over2wk'  => 'boolean',
        'sym_hemoptysis'     => 'boolean',
        'sym_cough_under2wk' => 'boolean',
        'sym_weight_loss'    => 'boolean',
        'sym_fever'          => 'boolean',
        'sym_night_sweat'    => 'boolean',
    ];

    /**
     * คะแนนของแต่ละอาการ ตามแบบคัดกรองของกรมควบคุมโรค
     * ห้ามแก้ตัวเลขเอง — เป็นเกณฑ์กลางที่ใช้ทั้งประเทศ
     */
    public static $symptomScore = [
        'sym_cough_over2wk'  => ['label' => 'ไอทุกวันเกิน 2 สัปดาห์', 'score' => 3],
        'sym_hemoptysis'     => ['label' => 'ไอเป็นเลือดใน 1 เดือนที่ผ่านมา', 'score' => 3],
        'sym_cough_under2wk' => ['label' => 'ไอน้อยกว่า 2 สัปดาห์', 'score' => 2],
        'sym_weight_loss'    => ['label' => 'น้ำหนักลดโดยไม่ทราบสาเหตุใน 1 เดือน', 'score' => 1],
        'sym_fever'          => ['label' => 'มีไข้ทุกวันนาน 1 สัปดาห์ ใน 1 เดือน', 'score' => 1],
        'sym_night_sweat'    => ['label' => 'เหงื่อออกมากผิดปกติตอนกลางคืนใน 1 เดือน', 'score' => 1],
    ];

    /** ตั้งแต่ 3 คะแนนขึ้นไป = ผู้มีอาการสงสัยวัณโรค ต้องส่งตรวจต่อ */
    public const SUSPECT_SCORE = 3;

    /** ระดับ HbA1c ของผู้ป่วยเบาหวาน — คุมน้ำตาลไม่ได้ทำให้การรักษาวัณโรคแย่ลง จึงต้องแยกให้เห็น */
    public static $dmHba1cOption = [
        1 => 'HbA1c < 7 (คุมได้)',
        2 => 'HbA1c ≥ 7 (คุมไม่ได้)',
        3 => 'ไม่ทราบระดับ',
    ];

    public static $labOption = [
        'sputum_afb'  => 'Sputum AFB',
        'molecular'   => 'Molecular test',
        'xpert'       => 'X-pert MTB/RIF',
        'lpa'         => 'LPA',
        'pcr'         => 'PCR',
        'culture_dst' => 'Culture/DST',
    ];

    public static $cxrOption = [
        1 => 'ปกติ',
        2 => 'เข้าได้กับวัณโรคปอด',
        3 => 'โรคปอดอื่นๆ',
        4 => 'ไม่ได้เอกซเรย์',
    ];

    /**
     * มีคอลัมน์ cxr_results (ผลเอกซเรย์หลายแถว) แล้วหรือยัง — migration 2026_10_02_100000 ต้องรอผู้ดูแลอนุมัติก่อนรัน
     * ระหว่างนั้นหน้าจอ/ตัวบันทึกถอยไปใช้คอลัมน์เดิมแถวเดียว ระบบจะได้ไม่พังถ้าโค้ดขึ้นก่อน migration
     */
    public static function hasCxrRows(): bool
    {
        static $has = null;
        return $has ??= \Illuminate\Support\Facades\Schema::hasColumn('tb_screenings', 'cxr_results');
    }

    /** แถวผลเอกซเรย์สำหรับแสดงในฟอร์ม — ข้อมูลเก่าที่มีแค่คอลัมน์เดิมแปลงเป็นแถวเดียว · ไม่มีอะไรเลย = แถวว่าง 1 แถว */
    public function cxrRows(): array
    {
        $rows = self::hasCxrRows() ? (array) ($this->cxr_results ?? []) : [];
        if (! $rows && ($this->cxr_result || $this->cxr_note)) {
            $rows = [['view' => null, 'result' => $this->cxr_result, 'note' => $this->cxr_note]];
        }
        return $rows ?: [['view' => null, 'result' => null, 'note' => null]];
    }

    /* ---------- ส่งตรวจทางห้องปฏิบัติการ (โครงใหม่ 2569-09-29) ---------- */

    public static $specimenOption = [
        'sputum_spot'   => 'เสมหะ (Spot)',
        'sputum_collect'=> 'เสมหะ (Collect)',
        'saliva'        => 'น้ำลาย',
        'gastric'       => 'น้ำล้างกระเพาะ',
        'tissue'        => 'ชิ้นเนื้อ / น้ำเจาะ',
        'other'         => 'อื่น ๆ',
    ];

    public static $molecularOption = [
        'xpert' => 'Xpert MTB/RIF',
        'lpa'   => 'LPA',
        'pcr'   => 'PCR',
    ];

    /** ผล AFB ตามใบรายงานของห้องแล็บ (กระดาษสีเหลือง) */
    public static $afbResultOption = [
        'N'      => 'N (Negative)',
        'scanty' => 'Scanty',
        '1+'     => '1+',
        '2+'     => '2+',
        '3+'     => '3+',
        'N/A'    => 'N/A (ไม่ได้ส่ง)',   // คลินิกขอ 2569-10-27 — แยก "ไม่ได้ส่งตรวจ" ออกจาก "ยังไม่ได้กรอก"
    ];

    /**
     * ส่วนเสริมของผล Molecular ตามใบรายงานแล็บ (คลินิกขอ 2569-10-28)
     *   1st LPA: INH · RIF   ·   2nd LPA: FLQs · AG/CP   ·   Xpert: Level · RIF Resistance (not detected = Susceptible)
     * เก็บต่อท้ายช่องผลเดิม "LPA: MTB detected · INH: Susceptible · RIF: Resistant" (ไม่เพิ่มคอลัมน์)
     * ตารางเสมหะ / รายงาน / ใบสรุป อ่านข้อความเดียวกันได้เลย
     */
    public static $molResistanceOption = [
        'Susceptible'   => 'Susceptible (ไวต่อยา · ไม่พบการดื้อ)',
        'Resistant'     => 'Resistant (ดื้อยา · พบการดื้อ)',
        'Inconclusive'  => 'Inconclusive (สรุปไม่ได้)',   // คลินิกใช้คำนี้ (2569-10-28) · ข้อความเก่า "Indeterminate" อ่านเป็นตัวนี้
    ];
    /** Xpert รายงานดื้อยาตัวเดียว "RIF resistance: Detected / Not detected" (คลินิกแจ้ง 2569-10-28) */
    public static $molDetectOption = ['Not detected' => 'Not detected (ไม่ดื้อ RIF)', 'Detected' => 'Detected (ดื้อ RIF)', 'Indeterminate' => 'Indeterminate'];
    public static $molLevelOption = ['Very low' => 'Very low', 'Low' => 'Low', 'Medium' => 'Medium', 'High' => 'High', 'Trace' => 'Trace'];

    /** หัวข้อเสริม => [ป้าย, ตัวเลือก, กลุ่ม] — ลำดับนี้คือลำดับที่ต่อท้ายข้อความ */
    public static function molExtraFields(): array
    {
        return [
            'INH'   => ['INH', self::$molResistanceOption, 'LPA 1st line'],
            'RIF'   => ['RIF', self::$molResistanceOption, 'LPA 1st line'],
            'FLQ'   => ['FLQs', self::$molResistanceOption, 'LPA 2nd line'],
            'AG/CP' => ['AG/CP', self::$molResistanceOption, 'LPA 2nd line'],
            'Level' => ['Level', self::$molLevelOption, 'Xpert'],
            'RIF resistance' => ['RIF resistance', self::$molDetectOption, 'Xpert'],
        ];
    }

    public static function molResistance($text): array
    {
        $out = [];
        foreach (self::molExtraFields() as $key => [$label, $opts]) {
            $alt = implode('|', array_map(fn ($o) => preg_quote($o, '/'), array_keys($opts))) . ($opts === self::$molResistanceOption ? '|Indeterminate|Resistance' : '');
            $out[$key] = '';
            if (preg_match('/(?<![A-Za-z])' . preg_quote($key, '/') . ':\s*(' . $alt . ')(?![A-Za-z])/i', (string) $text, $m)) {
                $v = ['indeterminate' => 'Inconclusive', 'resistance' => 'Resistant'][strtolower($m[1])] ?? $m[1];
                foreach (array_keys($opts) as $o) { if (strcasecmp($o, $v) === 0) { $out[$key] = $o; } }
            }
        }
        return $out;
    }

    /** AFB ที่หมายถึง "ไม่ได้ส่งตรวจ" — ไม่ใช่ผลลบ ห้ามนับเป็นผลตรวจ */
    public static function afbNotSent($v): bool
    {
        return (bool) preg_match('~^\s*n\s*/\s*a\s*$~i', (string) $v);
    }

    public static $igraOption = [
        'positive'      => 'Positive',
        'negative'      => 'Negative',
        'indeterminate' => 'Indeterminate',
    ];

    /** มาแบบไหน — ตัดสินว่าต้องให้คะแนนอาการไหม (F/U ไม่ต้องให้คะแนนซ้ำ) */
    public const VISIT_FIRST = 1;
    public const VISIT_FU    = 2;

    public static $visitTypeOption = [
        self::VISIT_FIRST => 'คัดกรองครั้งแรก / Walk-in',
        self::VISIT_FU    => 'มาติดตาม (F/U)',
    ];

    public static $impressionOption = [
        1 => 'normal (ไม่เป็นวัณโรค)',
        2 => 'LTBI (วัณโรคแฝง)',
        3 => 'TB',
        4 => 'Pulmonary TB (วัณโรคปอด)',
        5 => 'Extrapulmonary TB (วัณโรคนอกปอด)',
        // 7-9 เพิ่ม 2569-11-04 ให้รายงานประจำเดือนข้อ 2.2 / 2.3 / 2.6 นับได้ (เดิมขึ้น "ไม่มีในระบบ")
        7 => 'R/O TB (สงสัยวัณโรค รอผลเสมหะ)',
        8 => 'Non-TB (โรคทรวงอกอื่น ๆ)',
        9 => 'Inactive TB (วัณโรคระยะสงบ)',
        6 => 'อื่นๆ',
    ];

    /**
     * ผู้ป่วยใหม่มาด้วยเรื่องอะไร (คลินิกขอ 2569-11-04) — รายงานประจำเดือนข้อ 1.1.1–1.1.5
     * ว่าง (ใบเก่า) = รายงานเดาจากช่องผู้สัมผัส (contact_tb) แบบเดิม
     */
    public static $comeReasonOption = [
        1 => 'มีอาการสงสัยวัณโรค',
        2 => 'ผู้สัมผัสวัณโรค',
        3 => 'รับส่งต่อจากสถานพยาบาลอื่น',
        4 => 'มาขอคำปรึกษา',
        5 => 'Leprosy',
    ];

    /** มีคอลัมน์ come_reason แล้วหรือยัง (migration 2026_11_04_110000) */
    public static function comeReasonReady(): bool
    {
        static $ready = null;
        return $ready ??= \Illuminate\Support\Facades\Schema::hasColumn('tb_screenings', 'come_reason');
    }

    public static $afbStatusOption = [1 => 'M+ (พบเชื้อ)', 2 => 'M− (ไม่พบเชื้อ)'];

    /** ตำแหน่งที่ผู้ป่วยต้นทาง (index case) เป็นวัณโรค — ตามข้อความในแบบฟอร์ม Form 2 ข้อ 2 */
    public static $indexDiagnosisOption = [
        'ปอด', 'หลอดลม', 'กล่องเสียง', 'หลังโพรงจมูก', 'อื่นๆ',
    ];

    /* ---------- บันทึกส่วน "ส่งตรวจทางห้องปฏิบัติการ" (ที่เดียว ใช้ร่วมกันทั้งหน้าตรวจ OPD และบัตร TB 01) ----------
     *
     * เดิมอยู่ใน VisitController::saveTbScreening — แยกออกมา (2569-10-02) เพราะบัตร TB 01 ก็สั่ง/ลงผลส่วนนี้ได้แล้ว
     * สองหน้าต้องตีความค่าเหมือนกันทุกตัว ไม่งั้นสั่งจากที่หนึ่งแล้วอีกที่เห็นไม่ตรงกัน
     *
     * ข้อมูลแบ่งเป็นกลุ่ม แต่ละกลุ่ม "เขียนเฉพาะเมื่อถูกส่งมา" (มีตัวบอกกลุ่ม หรือมีคีย์ของกลุ่ม)
     * หน้าจอปิดช่องที่ไม่ได้แก้ไว้ไม่ให้ส่งมา -> หลายห้องเปิดค้างพร้อมกันก็ไม่ทับกัน
     *   specs  : lab_specimens_on + lab_specimens[...]  (หน้าเก่ามาก: lab_specimen ตัวเดียว)
     *   sec2   : lab_sec2_on + lab_methods / lab_molecular / ผล AFB / Molecular / Culture
     *   sec3   : lab_sec3_on + TST / Cy-TB / skin อื่น / IGRA  (checkbox ไม่ติ๊ก = ไม่มีคีย์ จึงต้องมีตัวบอก)
     *   labs_other, afb_status : มีคีย์ = เขียน
     */

    /** ชื่อช่องของส่วนนี้ — หน้าเก่าที่เปิดค้างยังส่งมาใน tb[...] ใช้แยกว่าเป็นหน้าเก่าหรือไม่ */
    public const LAB_LEGACY_KEYS = ['lab_specimens_on', 'lab_specimen', 'lab_sec2_on', 'labs_other', 'skin_other_name'];

    /**
     * เติมค่าส่วนส่งตรวจจากข้อมูลฟอร์มลงโมเดล (ยังไม่ save)
     *
     * @param bool $legacyFullForm หน้าเก่าที่ส่งทั้งใบใน tb[...] (ไม่มีตัวบอก lab_sec3_on) — ข้อ 3 เขียนทุกครั้งแบบเดิม
     */
    public function fillLabInput(array $in, bool $legacyFullForm = false): void
    {
        $text = fn ($k) => isset($in[$k]) && trim((string) $in[$k]) !== '' ? trim((string) $in[$k]) : null;
        $mm   = fn ($k) => isset($in[$k]) && trim((string) $in[$k]) !== '' ? (float) $in[$k] : null;

        // 1) สิ่งส่งตรวจ — หลายแถว {type, other, date} เก็บเฉพาะแถวที่เลือกชนิดแล้ว
        // ไม่มีตัวบอกชุดใหม่ = หน้าเก่าที่เปิดค้าง (ส่ง lab_specimen ตัวเดียว) — แปลงเป็นแถวเดียว ไม่ลบของเดิม (รีวิว 2569-10-01)
        $specRows = (array) ($in['lab_specimens'] ?? []);
        if (! array_key_exists('lab_specimens_on', $in) && ! empty($in['lab_specimen'])) {
            $specRows = [['type' => $in['lab_specimen'], 'other' => $in['lab_specimen_other'] ?? '',
                          'date' => $in['lab_sent_date'] ?? '']];
        }
        if (array_key_exists('lab_specimens_on', $in) || ! empty($in['lab_specimen'])) {
            $specs = self::cleanSpecimens($specRows);
            $this->lab_specimens = $specs ?: null;

            // คอลัมน์เดิมยังเขียนต่อ (ใช้แถวแรก) ที่อื่นที่อ่านคอลัมน์เดิมอยู่จะได้ไม่พัง
            $first = $specs[0] ?? null;
            $this->lab_specimen       = $first['type'] ?? null;
            $this->lab_specimen_other = $first['other'] ?? null;
            $this->lab_sent_date      = $first['date'] ?? null;
        }

        // 2) วิธีตรวจหาเชื้อ + ผล — เขียนเฉพาะเมื่อข้อ 2 เปิดให้แก้อยู่ (มีตัวบอก lab_sec2_on ส่งมา)
        // เดิมไม่มีสิ่งส่งตรวจ = ล้างวิธีตรวจและผลทิ้ง ทำให้ผลที่บันทึกไว้แล้วหายตอนแก้ช่องอื่น (รีวิว 2569-10-01)
        // ช่องที่หน้าจอปิดอยู่ (ไม่ได้ติ๊กวิธีนั้น) จะไม่ถูกส่งมา -> ผลของวิธีนั้นเป็นว่าง
        if (array_key_exists('lab_sec2_on', $in)) {
            $this->lab_methods = array_values(array_intersect(
                (array) ($in['lab_methods'] ?? []), ['afb', 'molecular', 'culture_dst']
            )) ?: null;
            $this->lab_molecular = array_values(array_intersect(
                (array) ($in['lab_molecular'] ?? []), array_keys(self::$molecularOption)
            )) ?: null;
            // ผลแต่ละช่อง: ไม่ติ๊กวิธีนั้น = ว่าง (เหมือนเดิม) · ติ๊กแต่ไม่ได้ส่งคีย์มา = คงค่าเดิม
            // ตัวหลังมีไว้ให้ปุ่ม "สั่งแล็บ" (LabCatalogOrder) ติ๊กวิธีตรวจเพิ่มโดยไม่ต้องส่งผลกลับมา
            // ไม่งั้นผลที่ส่งกลับเป็นค่าเก่าในใบ ไปทับผลที่เพิ่งลงในตารางเสมหะของบัตร TB 01 (2569-10-05)
            $m = (array) $this->lab_methods;
            $pick = function ($key, $method, $value) use ($in, $m) {
                if (! in_array($method, $m, true)) { return null; }
                return array_key_exists($key, $in) ? $value : $this->getAttribute($key);
            };
            // AFB 3 ช่อง: Spot = lab_afb_result (คอลัมน์เดิม ใบเก่าที่มีผลช่องเดียวจึงอ่านเป็น Spot) · Collect 1/2 คอลัมน์ใหม่
            $afb = fn ($k) => isset(self::$afbResultOption[$in[$k] ?? '']) ? $in[$k] : null;
            $this->lab_afb_result = $pick('lab_afb_result', 'afb', $afb('lab_afb_result'));
            if (self::hasAfbCollect()) {
                $this->lab_afb_collect1 = $pick('lab_afb_collect1', 'afb', $afb('lab_afb_collect1'));
                $this->lab_afb_collect2 = $pick('lab_afb_collect2', 'afb', $afb('lab_afb_collect2'));
            }
            $this->lab_molecular_result = $pick('lab_molecular_result', 'molecular', $text('lab_molecular_result'));
            $this->lab_culture_result   = $pick('lab_culture_result', 'culture_dst', $text('lab_culture_result'));
        }

        // 3) การตรวจหาการติดเชื้อ (skin test / IGRA)
        if (array_key_exists('lab_sec3_on', $in) || $legacyFullForm) {
            $this->tst_done        = ! empty($in['tst_done']);
            $this->tst_mm          = $mm('tst_mm');
            $this->cytb_done       = ! empty($in['cytb_done']);
            $this->cytb_mm         = $mm('cytb_mm');
            $this->skin_other_name = $text('skin_other_name');
            $this->skin_other_mm   = $mm('skin_other_mm');
            $this->igra_done       = ! empty($in['igra_done']);
            $this->igra_result     = isset(self::$igraOption[$in['igra_result'] ?? '']) ? $in['igra_result'] : null;
        }

        // การตรวจอื่นที่ส่งพร้อมกัน · ผลเสมหะ M+/M− (ช่องลงผลที่วางต่อจากส่วนส่งตรวจ)
        if (array_key_exists('labs_other', $in)) { $this->labs_other = $text('labs_other'); }
        if (array_key_exists('afb_status', $in)) {
            $this->afb_status = isset($in['afb_status']) && $in['afb_status'] !== '' ? (int) $in['afb_status'] : null;
        }
    }

    /** แถวสิ่งส่งตรวจที่ใช้ได้ — วันที่ผิดรูปแบบเก็บเป็นว่าง (th2dbDate ไม่เช็คค่า ปล่อยไปหน้าจะพัง) */
    public static function cleanSpecimens(array $rows): array
    {
        $specs = [];
        foreach ($rows as $row) {
            if (! is_array($row)) { continue; }
            $type = trim((string) ($row['type'] ?? ''));
            if (! isset(self::$specimenOption[$type])) { continue; }
            $other = trim((string) ($row['other'] ?? ''));
            $date  = trim((string) ($row['date'] ?? ''));
            $specs[] = [
                'type'  => $type,
                'other' => $other !== '' ? $other : null,
                // ตัวอ่านกลางของงาน TB (2569-10-05): ค.ศ. แปลงให้ · วันไม่มีจริง/ปีนอกช่วง = ว่าง (เดิม th2dbDate ล้นวันที่เงียบ ๆ)
                // หน้าตรวจเรียกผ่าน VisitController@update ซึ่งตีกลับทั้งหน้าไม่ได้ — ช่องขึ้นกรอบแดงฝั่งเบราว์เซอร์แทน
                // บัตร TB 01 (saveLabOrder) ตรวจด้วยตัวเดียวกันก่อนถึงตรงนี้แล้ว
                'date'  => \App\Support\ThaiDate::parse($date, ['allowEmpty' => true])['be'],
            ];
        }
        return $specs;
    }

    /** ฟอร์มส่วนนี้มีอะไรกรอกจริงไหม — ใช้ตัดสินว่าควรสร้างใบใหม่หรือไม่ (ไม่สร้างใบเปล่า) */
    public static function labInputHasValue(array $in): bool
    {
        if (self::cleanSpecimens((array) ($in['lab_specimens'] ?? [])) || ! empty($in['lab_specimen'])) { return true; }
        foreach (['lab_methods', 'lab_molecular', 'tst_done', 'cytb_done', 'igra_done'] as $k) {
            if (! empty($in[$k])) { return true; }
        }
        foreach (['lab_afb_result', 'lab_afb_collect1', 'lab_afb_collect2', 'lab_molecular_result', 'lab_culture_result', 'tst_mm', 'cytb_mm',
                  'skin_other_name', 'skin_other_mm', 'igra_result', 'labs_other', 'afb_status'] as $k) {
            if (isset($in[$k]) && trim((string) $in[$k]) !== '') { return true; }
        }
        return false;
    }

    /**
     * บันทึกส่วนส่งตรวจของ visit — แตะเฉพาะช่องของส่วนนี้ ไม่ยุ่งประวัติ/ตรวจร่างกาย/CXR/Impression
     * ยังไม่มีใบของ visit นี้ -> สร้างให้เมื่อมีอะไรกรอกจริงเท่านั้น
     * เรียกจาก VisitController@update (ช่อง tb_lab[...]) และ TbController@saveLabOrder (บัตร TB 01)
     */
    public static function saveLabSection(Visit $visit, array $in): ?self
    {
        $sc = self::where('visit_id', $visit->id)->first();
        if (! $sc) {
            if (! self::labInputHasValue($in)) { return null; }
            $sc = new self(['visit_id' => $visit->id]);
            $sc->patient_id = $visit->patient_id;
            $sc->created_by = auth()->id();
        }

        $sc->fillLabInput($in);
        $sc->save();

        // คนไข้ที่มีบัตร TB 01 ที่ยังรักษาอยู่ -> ผล AFB/Molecular/Culture ลงตารางผลเสมหะของบัตรด้วย (ค่าชุดเดียวกัน)
        TbSputumExam::syncFromLab($visit, $sc, $in);

        return $sc;
    }

    /** มีคอลัมน์ผล AFB Collect 1/2 แล้วหรือยัง (migration 2026_10_05_100000) — ก่อนรัน คนไข้คัดกรองกรอกได้แค่ Spot */
    public static function hasAfbCollect(): bool
    {
        static $has = null;
        return $has ??= \Illuminate\Support\Facades\Schema::hasColumn('tb_screenings', 'lab_afb_collect2');
    }

    public function householdMembers()
    {
        return $this->hasMany(TbHouseholdMember::class, 'tb_screening_id')->orderBy('id');
    }

    /**
     * ใบคัดกรองครั้งล่าสุดของคนไข้คนนี้ (ไม่นับ visit ที่กำลังเปิดอยู่)
     *
     * ใช้ยกข้อมูลที่ "ไม่เปลี่ยนทุกครั้ง" มาให้เอง — โรคประจำตัว ประวัติสัมผัส ผู้สัมผัสร่วมบ้าน
     * พยาบาลจะได้ไม่ต้องคีย์ซ้ำทุก visit (คลินิกขอ 2569-09-23)
     * ส่วนอาการ/คะแนน/ผลตรวจ/Impression ไม่ยก เพราะเป็นของการมาครั้งนั้น ๆ
     */
    public static function latestForPatient(int $patientId, ?int $exceptVisitId = null): ?self
    {
        // ข้ามใบที่มีแต่ผลแล็บ/เอกซเรย์ (สั่งจากบัตร TB 01) — ไม่มีประวัติให้ยกมา ถ้าหยิบใบนั้นจะได้ช่องว่างหมด (2569-10-02)
        return self::with('householdMembers')
            ->where('patient_id', $patientId)
            ->when($exceptVisitId, fn ($q) => $q->where('visit_id', '<>', $exceptVisitId))
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->first(fn ($s) => $s->hasFormData());
    }

    /**
     * ใบนี้เคยกรอกผ่านฟอร์มซักประวัติของหน้าตรวจแล้วหรือยัง
     *
     * ใบถูกสร้างได้จากที่อื่นด้วย (สั่งตรวจจากบัตร TB 01 / ผลเอกซเรย์ / รหัสโรค) ใบพวกนั้นมีแต่ส่วนของตัวเอง
     * หน้าตรวจต้องนับว่า "ยังไม่ได้เริ่มกรอก" — ยังยกข้อมูลจากครั้งก่อนมาให้ และยังเดาประเภทการมา (F/U) ได้
     * ไม่งั้นแค่สั่งแล็บจากบัตร หน้าตรวจจะกลายเป็นใบว่าง + เด้งเป็นเคสครั้งแรก (2569-10-02)
     */
    /** มีคอลัมน์ pe_note แล้วหรือยัง (migration 2026_10_08_110000) */
    /** บันทึกทางการพยาบาล (migration 2026_10_14_100000) */
    public static function nursingNoteReady(): bool
    {
        static $ready = null;
        return $ready ??= \Illuminate\Support\Facades\Schema::hasColumn('tb_screenings', 'nursing_note');
    }

    public static function peNoteReady(): bool
    {
        static $ready = null;
        return $ready ??= \Illuminate\Support\Facades\Schema::hasColumn('tb_screenings', 'pe_note');
    }

    public function hasFormData(): bool
    {
        if (! $this->exists) { return false; }

        foreach (['visit_type', 'come_reason', 'chief_complaint', 'illness_history', 'contact_tb', 'contact_tb_detail', 'previous_tb',
                  'previous_tb_detail', 'underlying_disease', 'arv_detail', 'dm_hba1c', 'index_name', 'ct_other',
                  'pe_ga', 'pe_heent', 'pe_heart', 'pe_chest', 'pe_abdomen', 'pe_ext', 'pe_neuro', 'pe_note', 'lung_note',
                  'impression', 'impression_site', 'impression_other', 'plan', 'nursing_note'] as $f) {
            $v = $this->getAttribute($f);
            if ($v !== null && $v !== '') { return true; }
        }
        foreach (array_merge(array_keys(self::$symptomScore),
                 ['has_arv', 'has_dm', 'ct_same_house', 'ct_same_room', 'ct_aircon', 'ct_close', 'ct_close_8h', 'ct_close_120h', 'ct_low_risk']) as $f) {
            if ($this->getAttribute($f)) { return true; }
        }
        return $this->householdMembers()->exists();
    }

    public function visit()
    {
        return $this->belongsTo(Visit::class, 'visit_id');
    }

    public function registration()
    {
        return $this->belongsTo(TbRegistration::class, 'tb_registration_id');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    /** บัตร TB ของผู้ป่วยต้นเหตุ (index case) ที่ผู้สัมผัสรายนี้สัมผัส — 2569-10-22 */
    public function indexCase()
    {
        return $this->belongsTo(TbRegistration::class, 'index_registration_id');
    }

    /** คิดคะแนนจากอาการที่ติ๊ก — เรียกทุกครั้งก่อนบันทึก ไม่ให้คะแนนกับอาการเพี้ยนกัน */
    public function calcScore(): int
    {
        $sum = 0;
        foreach (self::$symptomScore as $field => $info) {
            if ($this->$field) { $sum += $info['score']; }
        }
        return $sum;
    }

    public function isSuspect(): bool
    {
        return $this->symptom_score >= self::SUSPECT_SCORE;
    }

    /** ผลคัดกรองที่ควรเปิดบัตรต่อ — TB ทุกแบบ และ LTBI */
    public function needsRegistration(): bool
    {
        return in_array((int) $this->impression, [2, 3, 4, 5], true);
    }

    /** ประเภทบัตรที่ควรเปิดจากผลคัดกรองนี้ */
    public function suggestedRegistryType(): int
    {
        return (int) $this->impression === 2 ? TbRegistration::TYPE_LTBI : TbRegistration::TYPE_TB;
    }

    public function auditLabel(): string
    {
        return 'ใบคัดกรองวัณโรค (visit ' . $this->visit_id . ')';
    }
}
