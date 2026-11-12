<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Utillity;
use Illuminate\Database\Eloquent\Model;

/**
 * ทะเบียนวัณโรคหนึ่งคอร์สการรักษา (บัตร TB 01)
 *
 * ผูกกับ "คนไข้" ไม่ใช่ "การมาตรวจครั้งใดครั้งหนึ่ง" เพราะคอร์สหนึ่งยาว 6 เดือน–2 ปี
 * คนไข้คนเดียวมีได้หลายคอร์ส (รักษาซ้ำ/กลับเป็นซ้ำ) — ดูเหตุผลเต็มใน migration 2026_09_18_090000
 *
 * วันที่ทุกช่องเก็บเป็น พ.ศ. (Y-m-d) เหมือน visits.date · ห้าม Carbon::parse() ตรง ๆ
 */
class TbRegistration extends Model
{
    use Auditable;
    use \App\Models\Concerns\HasIcd10Codes;

    /** ชื่อเจ้าของใน icd10_entries — ต้องตรงกับที่ controller ส่งให้ Icd10Code::syncFor() */
    protected static $icd10Owner = 'tb_registration';

    protected $guarded = ['id'];

    /** จำวันเริ่มนับเดือนไว้ต่อหนึ่ง request (หน้าบัตรเรียกหลายรอบ) — ไม่ใช่คอลัมน์ */
    protected $countStartMemo = [];

    protected $casts = [
        'symptoms'  => 'array',
        'drug_sigs' => 'array',   // วิธีใช้ยาแต่ละตัว {"H": {qty, unit, freq, route, timing}} (2569-09-29)
        'monitor_plan' => 'array', // ตารางติดตามที่แพทย์กำหนดเอง (สูตร Individualized) — TbMonitorDefinitions::custom() (2569-10-22)
    ];

    /* ---------- วิธีใช้ยา (Sig) — พิมพ์ลงสติกเกอร์ยา (คลินิกขอ 2569-09-29) ----------
       เก็บเป็นรหัสย่อทางการแพทย์ (OD, PO, ac ...) แล้วแปลเป็นภาษาคนไข้ตอนพิมพ์ */

    public static $sigUnit = ['เม็ด', 'แคปซูล', 'ซอง', 'มล.', 'ขวด', 'หลอด'];

    /** ความถี่: รหัส => [ข้อความบนฉลาก, จำนวนครั้งต่อวัน] — สัปดาห์ละครั้งใช้กับสูตร 3HP */
    public static $sigFreq = [
        'OD'  => ['วันละ 1 ครั้ง', 1],
        'BID' => ['วันละ 2 ครั้ง', 2],
        'TID' => ['วันละ 3 ครั้ง', 3],
        'QID' => ['วันละ 4 ครั้ง', 4],
        'QW'  => ['สัปดาห์ละ 1 ครั้ง', 1 / 7],
        'TIW' => ['สัปดาห์ละ 3 ครั้ง', 3 / 7],
        // ยาอื่นที่หมอสั่งเพิ่ม (Para, Ibuprofen ...) กินเมื่อมีอาการ — คิดยอดจากวันไม่ได้ ใช้ "จ่ายทั้งหมด" แทน (2569-10-01)
        'PRN' => ['เมื่อมีอาการ', null],
    ];

    /**
     * รหัสนี้เป็นยาวัณโรค (มีในรายการยา TB / ยาเม็ดรวม / ชื่อสูตร TPT) ไหม
     * ไม่ใช่ = ยาอื่นที่หมอเพิ่มเอง เช่น Para, Ibuprofen — สติกเกอร์ไม่พิมพ์ "ยาวัณโรค กินจนครบ" และใช้จำนวนจ่ายที่กรอกเอง
     */
    /**
     * ยาที่ต้องให้ Pyridoxine (B6) คู่กัน ตามสูตรมาตรฐาน (คลินิกขอ 2569-10-12 — เดิมเติม B6 ทุกสูตรยกเว้น TPT)
     * Isoniazid (H / Hhd / เม็ดรวม 4FDC 2FDC) · Cycloserine · Ethionamide · Prothionamide
     * (รายการยาตามที่คลินิกยืนยัน 2569-10-14: INH, Cs, Eto, Pto)
     * สูตร 3HP (H-3HP / เม็ดรวม HP-FDC) ไม่ให้ B6 — คลินิกยืนยัน 2569-10-14 · หมอสั่งเพิ่มเองได้
     */
    public static $b6With = ['H', 'INH', 'Hhd', 'H-1HP', '4FDC', '2FDC', 'Cs', 'Eto', 'Pto'];   // H-1HP = INH ของสูตร 1HP (มี B6 — คลินิกยืนยัน 2569-11-03)

    public static function needsB6(array $drugs): bool
    {
        return (bool) array_intersect(array_map('strval', $drugs), self::$b6With);
    }

    public static function isTbDrugKey(string $key): bool
    {
        static $catalog = null;
        $catalog = $catalog ?? TbDrug::where('active', true)->get();
        if (preg_match('/fdc/i', $key)) { return true; }
        $alias = ['H' => ['h', 'inh'], 'INH' => ['h', 'inh'], 'H-3HP' => ['h', 'inh'], 'H-1HP' => ['h', 'inh'], 'P-1HP' => ['p']];
        $codes = $alias[$key] ?? [strtolower($key)];
        $norm  = fn ($s) => strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $s));
        return $catalog->contains(fn ($d) => (preg_match('/\(([^)]+)\)/', (string) $d->name, $m)
                && in_array(strtolower(rtrim($m[1], '.')), $codes, true))
            || ($norm($key) !== '' && str_starts_with($norm($d->name), $norm($key))));
    }

    // ชุดเดียวกับ App\Support\DrugSig::$route (ตัวแปลงข้อความฉลาก) — เดิมมีแค่ PO/IM/IV ยาทาที่หมอสั่งเพิ่มในกล่องจึงเลือกไม่ได้ (คลินิกแจ้ง 2569-10-26)
    public static $sigRoute = [
        'PO'  => 'รับประทาน',
        'TOP' => 'ทาบริเวณที่เป็น',
        'EYE' => 'ใช้กับตา',
        'PV'  => 'สอดช่องคลอด',
        'IM'  => 'ฉีดเข้ากล้ามเนื้อ',
        'IV'  => 'ฉีดเข้าหลอดเลือดดำ',
        'SC'  => 'ฉีดใต้ผิวหนัง',
        'ID'  => 'ฉีดเข้าชั้นผิวหนัง',
    ];

    /** เวลาที่สัมพันธ์กับมื้ออาหาร — ac = ก่อนอาหาร · pc = หลังอาหาร · hs = ก่อนนอน */
    public static $sigTiming = [
        'ac_bf' => 'ก่อนอาหารเช้า (ท้องว่าง)',
        'ac'    => 'ก่อนอาหาร',
        'pc_bf' => 'หลังอาหารเช้า',
        'pc'    => 'หลังอาหาร',
        'pc_imm' => 'หลังอาหารทันที',
        'cc'    => 'พร้อมอาหาร',
        'hs'    => 'ก่อนนอน',
    ];

    /** วิธีใช้ของยาตัวหนึ่ง (ค่าว่างถ้ายังไม่ได้ตั้ง) */
    public function sigFor(string $key): array
    {
        $all = (array) ($this->drug_sigs ?? []);
        return (array) ($all[$key] ?? []);
    }

    /**
     * ข้อความวิธีใช้บนฉลาก เช่น "รับประทาน ครั้งละ 1 เม็ด วันละ 1 ครั้ง ก่อนนอน"
     * คืนค่าว่างถ้ายังไม่ได้ตั้ง ให้ผู้เรียกใช้ข้อความแบบเดิมแทน
     * ใช้ตัวแปลงชุดเดียวกับหน้าสั่งยาคลินิกหลัก (App\Support\DrugSig) ข้อความจะได้ตรงกันทุกที่
     * บัตร TB ไม่มีจำนวนวันในวิธีใช้ (จ่ายตามรอบนัด) และไม่ระบุวิธีให้ยา = รับประทาน
     */
    public static function sigText(array $sig): string
    {
        return \App\Support\DrugSig::text(['route' => ($sig['route'] ?? '') ?: 'PO', 'days' => ''] + $sig);
    }

    /**
     * จำนวนที่ต้องจ่ายตามวิธีใช้ × จำนวนวัน — คืน null ถ้าคิดไม่ได้ (ไม่ได้กรอกจำนวนต่อครั้ง)
     * ยาสัปดาห์ละครั้งปัดจำนวนครั้งขึ้น: จ่าย 28 วัน = 4 ครั้ง, 30 วัน = 5 ครั้ง (กันยาขาดก่อนนัด)
     */
    public static function sigTotal(array $sig, int $days, ?string $startBe = null): ?float
    {
        if (! isset(self::$sigFreq[$sig['freq'] ?? ''])) { return null; }   // บัตร TB ไม่มี STAT/PRN
        $p = self::$sigFreq[$sig['freq']][1];
        if ($p !== null && $p < 1) {
            $qty = \App\Support\DrugSig::dosePerDispense(\App\Support\DrugSig::qtyNum($sig['qty'] ?? ''), $sig['unit'] ?? '');
            $times = self::doseTimes((string) $sig['freq'], $days, (string) ($sig['note'] ?? ''), $startBe);
            return $qty > 0 && $times ? $qty * $times : null;
        }
        return \App\Support\DrugSig::total(['days' => (string) $days] + $sig);
    }

    /** วันในสัปดาห์ที่ติ๊กไว้หน้าช่องพิมพ์เพิ่ม ("อ" / "จ-พ-ศ") → เลข dayOfWeek ของ Carbon (อา = 0) */
    public static function noteWeekdays(string $note): array
    {
        $map = ['จันทร์' => 1, 'อังคาร' => 2, 'พุธ' => 3, 'พฤหัสบดี' => 4, 'พฤหัส' => 4, 'ศุกร์' => 5, 'เสาร์' => 6, 'อาทิตย์' => 0,
                'อา' => 0, 'พฤ' => 4, 'จ' => 1, 'อ' => 2, 'พ' => 3, 'ศ' => 5, 'ส' => 6];
        $tok = 'จันทร์|อังคาร|พุธ|พฤหัสบดี|พฤหัส|ศุกร์|เสาร์|อาทิตย์|อา|พฤ|จ|อ|พ|ศ|ส';
        if (! preg_match('/^((?:' . $tok . ')(?:\s*[-,]\s*|\s+)?)+(?=\s|$)/u', trim($note), $m)) { return []; }
        preg_match_all('/' . $tok . '/u', $m[0], $all);
        return array_values(array_unique(array_map(fn ($t) => $map[$t], $all[0])));
    }

    /**
     * จำนวนครั้งที่ต้องกินจากวันนี้ถึงวันนัด
     * รายวัน = ครั้งต่อวัน × วัน (วันนัดรับยาใหม่ ไม่นับ)
     * รายสัปดาห์ (3HP/BDQ TIW) = นับ "วันนี้เป็นครั้งที่ 1" ถึง "วันนัด" รวมสองหัว (คลินิกขอ 2569-10-27:
     *   กิน 3HP วันอังคาร นัดอังคารที่ 12 ต้องได้ 12 ครั้ง — เดิมไม่นับวันนัด ได้ 11 ครั้ง ต้องเลื่อนนัดออกไปอีกสัปดาห์)
     *   ติ๊กวันไว้ = นับวันนั้นจริงตามปฏิทิน · ไม่ได้ติ๊ก = ถือว่าวันนี้เป็นวันกิน
     */
    public static function doseTimes(string $freq, int $days, string $note = '', ?string $startBe = null): ?float
    {
        $p = self::$sigFreq[$freq][1] ?? ($freq === '' ? 1 : null);
        if ($p === null || $days < 1) { return null; }
        if ($p >= 1) { return $p * $days; }
        $wd = self::noteWeekdays($note);
        if ($wd) {
            $start = $startBe ? \Carbon\Carbon::parse(\App\Utillity::BE2AD($startBe)) : \Carbon\Carbon::today();
            $n = 0;
            for ($i = 0; $i <= $days; $i++) {
                if (in_array($start->copy()->addDays($i)->dayOfWeek, $wd, true)) { $n++; }
            }
            return (float) $n;
        }
        return $freq === 'QW' ? (float) (intdiv($days, 7) + 1) : (float) ceil($p * ($days + 1) - 1e-9);
    }

    /** ประเภททะเบียน — ทั้งสามกลุ่มใช้ทะเบียนชุดเดียวกันแต่คนละแบบฟอร์ม */
    public const TYPE_TB   = 1;
    public const TYPE_LTBI = 2;
    public const TYPE_MDR  = 3;

    public static $typeOption = [
        self::TYPE_TB   => 'วัณโรค (TB 01)',
        self::TYPE_LTBI => 'วัณโรคแฝง (LTBI)',
        self::TYPE_MDR  => 'วัณโรคดื้อยา (MDR-TB)',
    ];

    public static $classificationOption = [
        1 => 'วัณโรคปอด',
        2 => 'วัณโรคนอกปอด',
        3 => 'วัณโรคแฝง (LTBI)',   // คลินิกขอ 2569-10-27 — บัตรวัณโรคแฝงไม่ใช่ทั้งปอด/นอกปอด
    ];

    public static $registerTypeOption = [
        1 => 'ใหม่',
        2 => 'กลับเป็นซ้ำ',
        3 => 'รักษาซ้ำภายหลังล้มเหลว',
        4 => 'รักษาซ้ำภายหลังขาดยา',
        5 => 'รับโอน',
        6 => 'อื่นๆ',
    ];

    /** ตารางตรวจเสมหะ — เดือนที่ต้องตรวจต่างกันตามสูตร (ระบุไว้บนบัตร TB 01) */
    public static $sputumScheduleOption = [
        1 => 'New (สูตรที่ 1) — ตรวจเดือนที่ 0, 2(3), 5, 6',
        2 => 'History of previous treatment (สูตรที่ 2) — ตรวจเดือนที่ 0, 3(4), 5, 8',
    ];

    public static $sputumMonths = [
        1 => [0, 2, 3, 5, 6],
        2 => [0, 3, 4, 5, 8],
    ];

    public static $dotByOption = [
        1 => 'เจ้าหน้าที่สาธารณสุข',
        2 => 'อาสาสมัคร (อสม./อมม.)',
        3 => 'ญาติ',
        4 => 'กินยาเอง',
        5 => 'อื่นๆ',
    ];

    /** หน่วยของระยะเวลาอาการแรกรับ — เดิมเก็บแต่ตัวเลข พอบันทึกแล้วไม่รู้ว่า 2 วันหรือ 2 เดือน (2569-09-28) */
    public static $durationUnitOption = [
        'day'   => 'วัน',
        'week'  => 'สัปดาห์',
        'month' => 'เดือน',
        'year'  => 'ปี',
    ];

    /* ---------- แผน DOT (ใช้ในหน้าทะเบียน DOT รวม ให้เหมือนตารางที่พยาบาลทำใน Excel) ---------- */

    public const DOT_DAILY  = 1;
    public const DOT_WEEKLY = 2;

    public static $dotFrequencyOption = [
        self::DOT_DAILY  => 'กินยาทุกวัน',
        self::DOT_WEEKLY => 'สัปดาห์ละ 1 ครั้ง',
    ];

    /** ตัวเลือกที่เจอในตารางจริงของคลินิก — เป็นช่องพิมพ์ได้ ไม่ได้ล็อกไว้แค่นี้ */
    // ระยะ/เหตุผลการ DOT — ชุดที่พยาบาลกำหนด 2569-09-30 (ช่องยังพิมพ์เองได้ ค่าเก่าที่ไม่อยู่ในรายการยังแสดงตามเดิม)
    // ข้อ 7 ของรายงานประจำเดือนจับกลุ่มจากค่าพวกนี้ (App\Services\TbMonthlyReport::DOT_GROUPS) — เพิ่มค่าใหม่ต้องไปใส่กลุ่มด้วย
    public static $dotReasonOption = [
        'Active phase', 'MDR', 'Pre-XDR', 'XDR', 'LTBI/TPT',
        'เคสรักษายาก', 'เฝ้าระวังการขาดยา', 'เฝ้าระวังอาการข้างเคียง',
    ];

    // "ศูนย์บริการฯ" กับ "มาที่คลินิก" คือที่เดียวกัน (ศูนย์บริการเวชศาสตร์ป้องกัน) เหลือ "มาที่คลินิก" ตัวเดียว (2569-09-30)
    // ชุดที่คลินิกกำหนด 2569-10-14 (ช่องยังพิมพ์เองได้ ค่าเก่าเช่น "อสม./รพ.สต." "วิดีโอคอล" ยังแสดงตามเดิม)
    public static $dotChannelOption = ['มาที่คลินิก', 'Line OA', 'รพ.สต.', 'WeChat', 'VOT', 'Messenger'];

    /** ผู้รับผิดชอบเคส DOT: ศูนย์บริการ + ชื่อเจ้าหน้าที่ในระบบ (ไม่รวมบัญชีกลาง/นักเทคนิคการแพทย์) + ค่าที่เคยพิมพ์ไว้ */
    public static function dotOwnerOptions(): array
    {
        $staff = \App\Models\User::orderBy('name')->pluck('name')
            ->filter(fn ($n) => preg_match('/[\x{0E00}-\x{0E7F}]/u', (string) $n) && ! preg_match('/ทน\.|MT\./u', (string) $n));
        $used = self::whereNotNull('dot_owner')->distinct()->pluck('dot_owner');
        return collect(['ศูนย์บริการ'])->merge($staff)->merge($used)->map(fn ($x) => trim((string) $x))
            ->filter()->unique()->values()->all();
    }

    /** ช่องผู้ให้คำปรึกษา/สถานที่ตรวจ HIV (migration 2026_10_14_100000) */
    public static function hivPlaceReady(): bool
    {
        static $ready = null;
        return $ready ??= \Illuminate\Support\Facades\Schema::hasColumn('tb_registrations', 'hiv_test_place');
    }

    /**
     * DOT โดย อสม./รพ.สต. — เจ้าหน้าที่ในพื้นที่กำกับเอง คลินิกไม่ต้องลงรายวัน
     * จึงไม่แสดงในหน้า DOT แต่ยังบันทึกไว้ในบัตร (คลินิกขอ 2569-09-30)
     * ช่องนี้พิมพ์เองได้ จึงจับคำ อสม / รพ.สต / รพสต แทนการเทียบข้อความตรงตัว
     */
    public function isCommunityDot(): bool
    {
        return (bool) preg_match('/อสม|รพ\.?\s*สต/u', (string) $this->dot_channel);
    }

    public static $weekdayOption = [
        1 => 'จันทร์', 2 => 'อังคาร', 3 => 'พุธ', 4 => 'พฤหัสบดี',
        5 => 'ศุกร์', 6 => 'เสาร์', 7 => 'อาทิตย์',
    ];

    public static $bcgOption = [
        1 => 'เคย มีแผลเป็น BCG',
        2 => 'เคย ไม่มีแผลเป็น',
        3 => 'ไม่เคยได้รับ',
    ];

    public static $hivResultOption = [
        1 => 'Negative',
        2 => 'Positive',
        3 => 'Not available',
    ];

    public static $outcomeOption = [
        1 => 'รักษาหาย (Cured)',
        2 => 'รักษาครบ (Completed)',
        3 => 'ล้มเหลว (Failed)',
        4 => 'ตาย (Died)',
        5 => 'ขาดยา > 2 เดือนติดต่อกัน (Defaulted)',
        6 => 'โอนออก ไม่ทราบผลการรักษา (Transferred Out)',
        // เปลี่ยนเป็นวัณโรคดื้อยา = ปิดบัตรเดิม แล้วเปิดบัตรดื้อยาใบใหม่ (คลินิกตกลง 2569-10-22)
        7 => 'ย้ายไปรักษาวัณโรคดื้อยา (Moved to DR-TB)',
        // บัตรที่เปิดซ้ำคอร์สเดียวกัน — เก็บไว้ดูประวัติ ไม่นับในรายงาน (TbMonthlyReport ข้ามทั้งใบ)
        self::OUTCOME_VOID => 'ยกเลิก — บัตรซ้ำ (เก็บไว้ดูประวัติ ไม่นับในรายงาน)',
    ];

    public const OUTCOME_VOID = 8;

    /* ===== บัตรวัณโรคแฝง (LTBI) — หน้าตาแยกจาก TB 01 (คลินิกขอ 2569-10-27) ===== */
    public const OUTCOME_DEV_TB = 9;    // ป่วยเป็นวัณโรคระหว่าง/หลัง TPT
    public const OUTCOME_ADR    = 10;   // หยุดยาเพราะผลข้างเคียงรุนแรง

    /** ผลการรักษา TPT — ใช้คอลัมน์ outcome เดิม · รักษาครบ = 2 ตัวเดียวกับ TB (นัดติดตามหลังจบใช้ได้เหมือนกัน) */
    public static $ltbiOutcomeOption = [
        2  => 'รักษาครบถ้วน (Treatment Completed)',
        9  => 'ป่วยเป็นวัณโรค (Developed TB Disease)',
        5  => 'หยุดยากลางคัน / ขาดการรักษา (Lost to follow-up)',
        10 => 'หยุดยา เนื่องจากผลข้างเคียงจากยารุนแรง (Adverse drug reaction)',
        4  => 'เสียชีวิต (Died) — จากสาเหตุใด ๆ ระหว่างรับ TPT',
        6  => 'โอนออก (Transfer Out) — ย้ายไปรักษาต่อที่สถานพยาบาลอื่น',
        self::OUTCOME_VOID => 'ยกเลิก — บัตรซ้ำ (เก็บไว้ดูประวัติ ไม่นับในรายงาน)',
    ];

    /** กลุ่มเป้าหมายที่ให้ยาป้องกัน (TPT) */
    public static $tptTargetOption = [
        1 => 'ผู้สัมผัสร่วมบ้าน / ใกล้ชิดผู้ป่วยวัณโรคปอด',
        2 => 'ผู้ติดเชื้อ HIV',
        3 => 'ผู้ต้องขัง',
        4 => 'บุคลากรสาธารณสุข',
        5 => 'ผู้ป่วยฟอกไต / เตรียมปลูกถ่ายอวัยวะ',
        6 => 'ได้รับยากดภูมิคุ้มกัน / anti-TNF',
        7 => 'เบาหวาน / โรคปอดจากฝุ่นหิน (Silicosis)',
        9 => 'อื่น ๆ',
    ];

    public static $ltbiTestOption = [1 => 'TST (ทดสอบทางผิวหนัง)', 2 => 'IGRA (ตรวจเลือด)', 3 => 'ตรวจทั้ง TST และ IGRA', 4 => 'ไม่ได้ตรวจ'];
    public static $ltbiTestResultOption = [1 => 'บวก (Positive)', 2 => 'ลบ (Negative)', 3 => 'แปลผลไม่ได้ (Indeterminate)'];
    public static $cxrResultOption = [
        1 => 'ปกติ',
        2 => 'ผิดปกติ — ไม่เข้ากับวัณโรค',
        3 => 'ผิดปกติ — สงสัยวัณโรค (ต้องตรวจต่อก่อนให้ TPT)',
    ];

    /** มีคอลัมน์ของบัตร LTBI แล้วหรือยัง (migration 2026_10_27_150000) — ยังไม่มี = บัตร LTBI ไม่โชว์ช่องใหม่ */
    public static function ltbiReady(): bool
    {
        static $ready = null;
        return $ready ??= \Illuminate\Support\Facades\Schema::hasColumn('tb_registrations', 'tpt_target');
    }

    /**
     * บัตรของ visit หนึ่ง — visit วันนี้ = บัตรที่ยังรักษาอยู่ (เหมือนเดิม)
     * visit ย้อนหลัง = บัตรที่ "ครอบวันนั้น" (เริ่มก่อน/วันนั้น และยังไม่จบ ณ วันนั้น) แม้วันนี้ปิดบัตรไปแล้ว
     * (คลินิกแจ้ง 2569-10-27: ลง visit 12/05 ย้อนหลังของคนที่รักษาหายแล้ว ระบบให้เปิดบัตรใหม่ ไม่ผูกกับบัตรเดิม)
     * บัตรซ้ำ (ยกเลิก) ไม่นับ · ครอบหลายใบ = ใบที่เริ่มหลังสุด (ใบซ้ำวันเริ่มเดียวกัน = ใบแรก)
     */
    public static function forVisit($visit): ?self
    {
        if (! $visit || ! $visit->patient_id) { return null; }
        $regs = self::where('patient_id', $visit->patient_id)
            ->where(fn ($q) => $q->whereNull('outcome')->orWhere('outcome', '!=', self::OUTCOME_VOID))
            ->orderByDesc('id')->get();
        $open = $regs->first(fn ($r) => $r->outcome === null);

        $d = (string) $visit->getRawOriginal('date');
        if (preg_match('/^(\d{4})(-\d{2}-\d{2})/', $d, $m)) {
            $d = ((int) $m[1] < 2500 ? (int) $m[1] + 543 : (int) $m[1]) . $m[2];   // ข้อมูลยุคแรกเก็บ ค.ศ.
        } else { $d = ''; }
        if ($d !== '' && $d < VisitAppointment::todayBe()) {
            $hit = $regs->filter(function ($r) use ($d) {
                $start = (string) ($r->getRawOriginal('treatment_start_date') ?: $r->getRawOriginal('register_date'));
                $end   = (string) $r->getRawOriginal('outcome_date');
                return $start !== '' && $start <= $d && ($r->outcome === null || $end === '' || $end >= $d);
            })->sortBy(fn ($r) => sprintf('%s-%010d', strtr((string) ($r->getRawOriginal('treatment_start_date') ?: $r->getRawOriginal('register_date')), '0123456789', '9876543210'), $r->id))->first();
            if ($hit) { return $hit; }
        }
        return $open;
    }

    public function isLtbi(): bool
    {
        return (int) $this->registry_type === self::TYPE_LTBI;
    }

    /** ชื่อบัตรบนปุ่ม/ป้าย — LTBI ไม่ใช่ "TB 01" (คลินิกขอ 2569-10-27) */
    public function cardName(): string
    {
        return $this->isLtbi() ? 'บัตรวัณโรคแฝง (LTBI)' : 'บัตร TB 01';
    }

    /** สีประจำบัตร: TB ส้มน้ำตาล · LTBI ม่วง (ตรงกับหัวบัตร) */
    public function cardColor(): string
    {
        return $this->isLtbi() ? '#6d28d9' : '#b45309';
    }

    /** ตัวเลือกผลการรักษาตามประเภทบัตร */
    public static function outcomeOptionsFor(?int $type): array
    {
        return (int) $type === self::TYPE_LTBI ? self::$ltbiOutcomeOption : self::$outcomeOption;
    }

    /** อาการแรกรับตามบัตร TB 01 (เก็บใน symptoms เป็น JSON พร้อมระยะเวลา) */
    /** อาการที่มีช่องระบุเพิ่ม (คลินิกขอ 2569-10-19: เสมหะสีอะไร · อื่นๆ คืออะไร) */
    public static $symptomDetail = ['sputum' => 'สีอะไร เช่น เหลือง เขียว', 'other' => 'ระบุอาการ'];

    /**
     * ข้อความจากใบคัดกรอง OPD + ทะเบียนคนไข้ สำหรับเติมช่องในบัตร TB 01 (คลินิกแจ้ง 2569-10-19: เปิดบัตรแล้วมาแค่โรคประจำตัว)
     * เดิมดึงเฉพาะช่อง "รายละเอียด" ของใบคัดกรอง — ส่วนใหญ่ว่าง (พยาบาลเลือกแค่ มี/ไม่มี/ปฏิเสธ) ช่องในบัตรเลยว่าง
     * ตอนนี้แปลงตัวเลือกเป็นข้อความด้วย + ผู้ป่วยต้นทาง/ผู้สัมผัสร่วมบ้าน (Form 2) + ประวัติแพ้ยาจากทะเบียนคนไข้
     * ใช้ใบคัดกรองของ visit ที่ระบุ ไม่มีก็ใช้ใบล่าสุดของคนไข้ · คืนค่าว่าง = ไม่มีข้อมูล
     */
    public static function opdCardText($patientId, ?int $visitId = null): array
    {
        $out = ['previous_tb_history' => '', 'contact_history' => '', 'drug_allergy' => '', 'other_disease' => ''];
        if (! $patientId) { return $out; }
        // แต่ละหัวข้อใช้ใบคัดกรองล่าสุดที่ "กรอกหัวข้อนั้นไว้จริง" (ใบของ visit ที่ระบุมาก่อน)
        // เดิมหยิบใบล่าสุดใบเดียว — คนไข้มา F/U หลายครั้ง ใบหลัง ๆ ว่าง เลยดึงประวัติรักษา/การสัมผัสไม่มา (คลินิกแจ้ง 2569-10-27)
        $all = TbScreening::where('patient_id', $patientId)->orderByDesc('id')->get();
        if ($visitId) {
            $all = $all->sortBy(fn ($s) => (int) $s->visit_id === $visitId ? 0 : 1)->values();
        }
        $yn = [1 => 'มี', 0 => 'ไม่มี', 2 => 'ปฏิเสธ'];
        foreach ($all as $sc) {
            if ($out['previous_tb_history'] === '') {
                $out['previous_tb_history'] = implode(' · ', array_filter([$yn[$sc->previous_tb] ?? null, trim((string) $sc->previous_tb_detail)]));
            }
            if ($out['contact_history'] === '') {
                $c = array_filter([$yn[$sc->contact_tb] ?? null, trim((string) $sc->contact_tb_detail)]);
                $idx = array_filter([trim((string) $sc->index_name), trim((string) $sc->index_diagnosis), trim((string) $sc->index_treat_hospital)]);
                if ($idx) { $c[] = 'ผู้ป่วยต้นทาง: ' . implode(' / ', $idx); }
                $members = $sc->householdMembers()->get()->filter(fn ($m) => trim((string) $m->name) !== '');
                if ($members->count()) { $c[] = 'ผู้สัมผัสร่วมบ้าน ' . $members->count() . ' คน (' . $members->pluck('name')->map(fn ($n) => trim($n))->implode(', ') . ')'; }
                $out['contact_history'] = implode(' · ', $c);
            }
            if ($out['other_disease'] === '') { $out['other_disease'] = trim((string) $sc->underlying_disease); }
            if ($out['previous_tb_history'] !== '' && $out['contact_history'] !== '' && $out['other_disease'] !== '') { break; }
        }
        $p = Patient::find($patientId);
        if ($p) {
            if (trim((string) $p->congenitaldisease) !== '') { $out['other_disease'] = trim((string) $p->congenitaldisease); }
            $out['drug_allergy'] = trim((string) $p->drug_allergy);
        }
        return $out;
    }

    public static $symptomOption = [
        'cough'       => 'ไอ',
        'sputum'      => 'เสมหะสี',
        'hemoptysis'  => 'เสมหะเป็นเลือด มีเลือดปน',
        'chest_pain'  => 'เจ็บหน้าอก',
        'dyspnea'     => 'เหนื่อย หอบ',
        'fever'       => 'ไข้',
        'other'       => 'อื่นๆ',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    /** บัตรของผู้ป่วยต้นเหตุ (index case) — ใช้กับบัตร LTBI / ผู้สัมผัสที่ป่วยเอง (2569-10-22) ดู App\Services\TbContacts */
    public function indexCase()
    {
        return $this->belongsTo(self::class, 'index_registration_id');
    }

    public function sputumExams()
    {
        return $this->hasMany(TbSputumExam::class)->orderBy('month_no');
    }

    public function xrays()
    {
        return $this->hasMany(TbXray::class)->orderBy('xray_date');
    }

    public function regimenChanges()
    {
        // เรียงตามวันที่เริ่ม (ไม่มีวันที่ = ช่วงแรกสุด) — เดิมเรียงตามเดือนที่เริ่ม+ลำดับที่สร้าง
        // ช่วงที่แทรกย้อนหลัง (ลงยาย้อนหลัง 2569-10-26) เลยไปต่อท้ายทั้งที่เกิดก่อน (คลินิกแจ้ง 2569-10-27)
        return $this->hasMany(TbRegimenChange::class, 'tb_registration_id')
            ->orderByRaw('start_date IS NOT NULL')->orderBy('start_date')->orderBy('id');
    }

    /**
     * ตั้ง "ช่วงสูตรแรก" ให้บัตรที่ยังไม่มีประวัติสูตรยา
     *
     * บัตรที่เปิดก่อนมีระบบประวัติสูตร (และบัตรที่พยาบาลพิมพ์สูตรลงช่องสูตรยาเฉย ๆ)
     * จะไม่มีช่วงแรกให้ปิดตอนเปลี่ยนสูตรกลางคอร์ส จึงสร้างให้จากสูตรในบัตร + วันเริ่มยา
     * ตัวยาเดาจากสูตรมาตรฐานที่ชื่อตรงกัน ถ้าไม่ตรงก็ปล่อยว่างไว้ให้พยาบาลติ๊กเอง (2569-09-24)
     */
    public function ensureInitialRegimenPeriod(): ?TbRegimenChange
    {
        $text = trim((string) $this->regimen);
        if ($text === '' || $this->regimenChanges()->exists()) { return null; }

        $match = TbRegimen::where('active', true)
            ->where(function ($q) use ($text) { $q->where('regimen', $text)->orWhere('name', $text); })
            ->orderByRaw('drugs IS NULL')   // แคตตาล็อกมีชื่อซ้ำ (BPalM / BPaLM) เอาแถวที่ผูกรายการยาไว้ก่อน
            ->first();

        return TbRegimenChange::create([
            'tb_registration_id' => $this->id,
            'regimen'     => $text,
            'category'    => $match->category ?? null,
            'start_month' => 0,
            'start_date'  => $this->treatment_start_date ?: $this->register_date,
            'reason'      => 'สูตรเริ่มต้นของคอร์สนี้',
            'drugs'       => $match && $match->drugs ? array_keys($match->drugs) : null,
            'created_by'  => auth()->id(),
        ]);
    }

    /**
     * น้ำหนักตั้งต้นของตัวคำนวณขนาดยา + ที่มาของตัวเลข
     *
     * ลำดับ: น้ำหนักที่ชั่งในหน้าตรวจ -> น้ำหนักที่เคยบันทึกไว้ในตารางขนาดยาของบัตร
     * (บางเคสไม่ได้ชั่งในหน้าตรวจ แต่พยาบาลกรอกน้ำหนักลงตารางยาไว้แล้ว 2569-09-24)
     *
     * @return array{0: float|null, 1: string|null}  [น้ำหนัก, ที่มา]
     */
    public function lastKnownWeight($visitWeight = null): array
    {
        if ($visitWeight) { return [(float) $visitWeight, 'ชั่งในหน้าตรวจครั้งนี้']; }

        $w = Visit::where('patient_id', $this->patient_id)
            ->whereNotNull('weight')->orderByDesc('date')->orderByDesc('id')->value('weight');
        if ($w) { return [(float) $w, 'ชั่งในหน้าตรวจครั้งล่าสุด']; }

        $row = $this->monthlyDoses()->whereNotNull('weight')->reorder()->orderByDesc('sort')->orderByDesc('id')->first();
        if ($row && $row->weight) { return [(float) $row->weight, 'ตารางขนาดยา ' . $row->labelForHumans()]; }

        return [null, null];
    }

    /** ช่วงสูตรที่ขึ้นต้นด้วยคำนี้ในเหตุผล = พยาบาลติ๊ก "หยุดสูตรเดิม แล้วเริ่มนับรอบใหม่" (นับรอบ 0 ใหม่แม้สูตรกลุ่มเดียวกัน) */
    public const RESTART_MARK = '[เริ่มนับใหม่]';

    /**
     * แบ่งคอร์สในบัตรเป็น "ช่วงนับรอบ" (คลินิกขอ 2569-10-26 — มีเคสที่กิน 2HRZE แล้วเปลี่ยนเป็น BPaL ในบัตรเดียว)
     * ช่วงใหม่เริ่มเมื่อ: เปลี่ยนไปสูตรคนละกลุ่ม (ไวต่อยา / BPaL / ดื้อยา / ป้องกัน) หรือติ๊กเริ่มนับใหม่ (RESTART_MARK)
     * เปลี่ยนในกลุ่มเดียวกัน (4FDC → 2FDC, BPaL → BPaLM) = ช่วงเดิม นับรอบต่อ
     * รอบสั่งยาอยู่ช่วงไหนดูจากวันที่จ่าย · เลขรอบซ้ำกันได้ถ้าคนละช่วง · ด.0 ของตารางติดตามและ "เดือนที่" นับจากต้นช่วงล่าสุด
     *
     * @return array<int, array{idx:int, start:?string, end:?string, regimen:string, group:?string}>  วันที่เป็น พ.ศ. Y-m-d
     */
    public function doseSegments(): array
    {
        $base = self::beDate($this->treatment_start_date ?: $this->register_date);
        $type = (int) $this->registry_type === self::TYPE_MDR ? self::TYPE_TB : (int) $this->registry_type;
        $rows = $this->id ? TbRegimenChange::where('tb_registration_id', $this->id)->get() : collect();
        // ช่วงที่ไม่มีวันเริ่ม (ลงไว้ก่อนมีช่องวันที่) บอกไม่ได้ว่าอยู่ตรงไหน — ไม่เอามาแบ่งช่วง ถ้ามีช่วงที่มีวันที่อยู่แล้ว
        if ($rows->contains(fn ($r) => self::beDate($r->start_date))) { $rows = $rows->filter(fn ($r) => self::beDate($r->start_date)); }
        $rows = $rows->sortBy(fn ($r) => [self::beDate($r->start_date) ?? $base ?? '', $r->id])->values();

        $segs = [];
        foreach ($rows as $r) {
            $start = self::beDate($r->start_date) ?? $base;
            $group = \App\Services\TbMonitor\TbMonitor::scheduleKeyFor($type, (string) $r->regimen);
            $last  = $segs ? $segs[count($segs) - 1] : null;
            $restart = strpos((string) $r->reason, self::RESTART_MARK) === 0;
            if ($last && $last['group'] === $group && ! $restart) {
                $segs[count($segs) - 1]['end'] = self::beDate($r->end_date);
                $segs[count($segs) - 1]['regimen'] = (string) $r->regimen;
                $segs[count($segs) - 1]['regimens'][] = (string) $r->regimen;
                continue;
            }
            if ($last && $segs[count($segs) - 1]['end'] === null) { $segs[count($segs) - 1]['end'] = $start; }
            $segs[] = ['idx' => count($segs), 'start' => $start, 'end' => self::beDate($r->end_date),
                       'regimen' => (string) $r->regimen, 'regimens' => [(string) $r->regimen], 'group' => $group];
        }
        if (! $segs) {
            $segs[] = ['idx' => 0, 'start' => $base, 'end' => null, 'regimen' => (string) $this->regimen, 'group' => null];
        }
        // ช่วงแรกเริ่มที่วันเริ่มรักษาของบัตรเสมอ — รอบที่ลงก่อนวันเริ่มสูตรแรกเล็กน้อยไม่หลุดไปช่วงไหน
        $segs[0]['start'] = $base && (! $segs[0]['start'] || $base < $segs[0]['start']) ? $base : $segs[0]['start'];
        return $segs;
    }

    /**
     * Sputum conversion ของสูตรไวต่อยา (ช่วงปัจจุบัน) — คลินิกขอ 2569-10-28
     *   AFB เดือน 2 ลบ = Converted · เดือน 2 บวก แล้วเดือน 3 ลบ = Delayed conversion · เดือน 3 ยังบวก = Non-conversion
     * อ่านจากตารางผลเสมหะ (Spot / Collect 1 / Collect 2 · N/A = ไม่ได้ส่ง) · คืน null = ไม่ใช่สูตรไวต่อยา
     */
    public function sputumConversion(): ?array
    {
        $segs = $this->doseSegments();
        $seg  = $segs[count($segs) - 1];
        if (($seg['group'] ?? null) !== 'ds') { return null; }
        $rows = $this->sputumExams()->get()->filter(fn ($r) => $this->segmentIndexFor($r->collected_date, $segs) === $seg['idx']);
        $res = function (string $label) use ($rows) {
            $hit = $rows->filter(fn ($r) => strtolower(trim((string) ($r->label !== null && $r->label !== '' ? $r->label : $r->month_no))) === $label);
            $vals = [];
            foreach ($hit as $r) { foreach ([$r->spot, $r->collect, $r->collect2 ?? null] as $v) { if (trim((string) $v) !== '') { $vals[] = $v; } } }
            return $vals ? \App\Services\TbMonitor\TbMonitorEngine::smearPositive($vals) : null;   // true=บวก false=ลบ null=ยังไม่มีผล
        };
        [$m0, $m2, $m3] = [$res('0'), $res('2'), $res('3')];
        if ($m0 === false && $m2 === null && $m3 === null) {
            return ['key' => 'base_neg', 'text' => 'เสมหะเริ่มต้นเป็นลบ — ไม่ประเมิน conversion', 'color' => '#475569', 'bg' => '#f1f5f9'];
        }
        if ($m2 === false) { return ['key' => 'converted', 'text' => 'Converted — AFB เดือนที่ 2 เป็นลบ', 'color' => '#166534', 'bg' => '#dcfce7']; }
        if ($m2 === true && $m3 === false) { return ['key' => 'delayed', 'text' => 'Delayed conversion — AFB เดือนที่ 3 เป็นลบ', 'color' => '#92400e', 'bg' => '#fef3c7']; }
        if ($m3 === true) { return ['key' => 'non', 'text' => 'Non-conversion — AFB เดือนที่ 3 ยังบวก (ส่ง Culture / DST)', 'color' => '#991b1b', 'bg' => '#fee2e2']; }
        if ($m2 === true) { return ['key' => 'pending3', 'text' => 'ยังไม่ convert — AFB เดือนที่ 2 บวก: ให้ระยะเข้มข้นต่อ แล้วตรวจเดือนที่ 3', 'color' => '#9a3412', 'bg' => '#ffedd5']; }
        return ['key' => 'pending2', 'text' => 'รอผล AFB สิ้นเดือนที่ 2', 'color' => '#475569', 'bg' => '#f1f5f9'];
    }

    /** ช่วงนับรอบของวันที่ (พ.ศ.) — ไม่มีวันที่ = ช่วงล่าสุด · ก่อนช่วงแรก = ช่วงแรก */
    public function segmentIndexFor(?string $dateBe, ?array $segs = null): int
    {
        $segs = $segs ?? $this->doseSegments();
        $d = self::beDate($dateBe);
        if (! $d) { return count($segs) - 1; }
        $idx = 0;
        foreach ($segs as $s) { if ($s['start'] && $s['start'] <= $d) { $idx = $s['idx']; } }
        return $idx;
    }

    /**
     * จัดค่า sort ของรอบสั่งยาใหม่ทั้งบัตร = ช่วง × 5000 + ตำแหน่งในช่วง — ให้ "ครั้งล่าสุด" ที่หลายที่หาจาก sort ยังถูก
     * แม้เลขรอบซ้ำกันข้ามช่วง (รอบ 3 ของ 2HRZE ต้องมาก่อนรอบ 0 ของ BPaL) · บัตรช่วงเดียวค่าเท่าเดิม
     * เขียนด้วย query ตรง ไม่ผ่าน model event (ไม่วนกลับมาเรียกตัวเอง ไม่ลง audit ซ้ำ)
     */
    public function resortDoses(): void
    {
        if (! $this->id) { return; }
        $segs = $this->doseSegments();
        foreach (TbMonthlyDose::where('tb_registration_id', $this->id)->get() as $r) {
            $sort = min(65000, $this->segmentIndexFor($r->dispensed_date, $segs) * 5000
                + TbMonthlyDose::sortValue($r->label, (int) $r->month_no));
            if ((int) $r->sort !== $sort) { TbMonthlyDose::whereKey($r->id)->update(['sort' => $sort]); }
        }
    }

    /** สูตรยาที่ใช้อยู่ตอนนี้ — จากประวัติสูตร ถ้ายังไม่มีก็ใช้ช่อง regimen ของบัตร */
    public function currentRegimenText(): ?string
    {
        $cur = TbRegimenChange::currentFor($this->id);
        return $cur->regimen ?? $this->regimen;
    }

    /**
     * รหัสยาที่ต้องมีช่องกรอกในตารางขนาดยารายเดือน
     *
     * ลำดับการหา: ยาของสูตรที่ใช้อยู่ -> ยาของสูตรมาตรฐานที่ชื่อตรงกัน -> ชุดมาตรฐาน H R Z E
     * (คลินิกขอให้เปลี่ยนตัวยาในตารางได้ เพราะเคสดื้อยาไม่ได้ใช้ Streptomycin แล้ว)
     */
    public function doseDrugKeys(): array
    {
        $keys = $this->withAlwaysOnDrugs($this->regimenDrugKeys());

        // 4FDC (ระยะเข้มข้น เดือน 1-2) ต้องมาก่อน 2FDC (ระยะต่อเนื่อง เดือน 3-6) ตามลำดับการรักษา
        // สูตรที่บันทึกไว้บางอันเก็บ 2FDC ไว้ก่อน ตารางเลยดูกลับหัว (คลินิกขอ 2569-10-02)
        $i4 = array_search('4FDC', $keys, true);
        $i2 = array_search('2FDC', $keys, true);
        if ($i4 !== false && $i2 !== false && $i4 > $i2) {
            [$keys[$i2], $keys[$i4]] = [$keys[$i4], $keys[$i2]];
        }
        return $keys;
    }

    /** ยาตามสูตรที่ใช้อยู่ (ยังไม่รวมยาที่ต้องมีทุกเคส) */
    private function regimenDrugKeys(): array
    {
        $cur = TbRegimenChange::currentFor($this->id);
        if ($cur && $cur->drugs) { return array_values($cur->drugs); }

        $text = trim((string) $this->regimen);
        if ($text !== '') {
            $match = TbRegimen::where('active', true)
                ->where(function ($q) use ($text) {
                    $q->where('regimen', $text)->orWhere('name', $text);
                })->orderByRaw('drugs IS NULL')->first();
            if ($match && $match->drugs) {
                $keys = array_keys($match->drugs);
                // สูตรมาตรฐานในตาราง tb_regimens ใส่ B6 ไว้ทุกสูตร — เคส TPT ห้ามได้ B6 มาจากตรงนี้
                // (หมอเลือก B6 เองผ่านประวัติเปลี่ยนสูตรได้ ซึ่งไปทางบรรทัดบน) รีวิว 2569-09-30
                if ((int) $this->registry_type === self::TYPE_LTBI) {
                    $keys = array_values(array_diff($keys, ['B6']));
                }
                return $keys;
            }
        }

        return TbMonthlyDose::$defaultDrugs;
    }

    /**
     * ยาที่ต้องมีช่องกรอกทุกเคส ไม่ว่าจะสูตรไหน
     *
     * B6 (Pyridoxine) 50 มก. ขนาดคงที่ — กินคู่ INH และ Lzd เสมอเพื่อกันปลายประสาทอักเสบ
     * คลินิกจึงขอให้มีคอลัมน์ B6 ติดอยู่ในตารางขนาดยาตลอด (2569-09-28)
     */
    private function withAlwaysOnDrugs(array $keys): array
    {
        // ยกเว้นวัณโรคแฝง (TPT) — คลินิกแจ้งว่า TPT ไม่ใช้ B6 (2569-09-29)
        // ต้องตัดที่นี่ ไม่ใช่แค่ในรายการเลือกยา ไม่งั้นตารางขนาดยา/วิธีใช้/สติกเกอร์ยังมี B6
        // และคะแนนเลือกกลุ่มยาจะเอียงไปกลุ่ม DS (รีวิว 2569-09-30)
        // ถ้าหมอใส่ B6 ในสูตรเองยังอยู่ครบ — ตัดแค่ตัวที่ระบบเติมให้
        if ((int) $this->registry_type === self::TYPE_LTBI) {
            return $keys;
        }
        foreach (['B6'] as $always) {
            if (! in_array($always, $keys, true)) { $keys[] = $always; }
        }

        return $keys;
    }

    public function monthlyDoses()
    {
        // เรียงตามลำดับในตาราง (มีแถว 2 สัปดาห์/นอกรอบ ปนกับแถวรายเดือน)
        return $this->hasMany(TbMonthlyDose::class)->orderBy('sort')->orderBy('month_no')->orderBy('id');
    }

    public function dotDays()
    {
        return $this->hasMany(TbDotDay::class)->orderBy('dot_date');
    }

    public function susceptibilities()
    {
        return $this->hasMany(TbDrugSusceptibility::class)->orderBy('sent_date');
    }

    public function auditLabel(): string
    {
        return 'ทะเบียนวัณโรค ' . ($this->tb_no ?: '#' . $this->id);
    }

    /** ยังรักษาอยู่ = ยังไม่ได้ลงผลการรักษา */
    public function isActive(): bool
    {
        return empty($this->outcome);
    }

    /** ป้ายสั้นบนรายชื่อคนไข้ (หน้าทะเบียนผู้ป่วย) — สีแยกตามความเสี่ยง ดื้อยาเข้มสุด */
    public const MARK = [
        self::TYPE_MDR  => ['label' => 'MDR-TB', 'bg' => '#7f1d1d', 'fg' => '#fff'],
        self::TYPE_TB   => ['label' => 'TB',     'bg' => '#dc2626', 'fg' => '#fff'],
        self::TYPE_LTBI => ['label' => 'LTBI',   'bg' => '#fde68a', 'fg' => '#78350f'],
    ];

    /**
     * ป้ายวัณโรคของคนไข้ที่ "ยังรักษาอยู่" = บัตร TB ที่ยังไม่ลงผลการรักษา (outcome ว่าง) — คลินิกขอ 2569-11-03
     * ลงผลการรักษาแล้ว (หาย/ครบ/ขาดยา/ย้าย ฯลฯ) ป้ายหายเอง ไม่ต้องมีใครไปลบ
     * คนเดียวมีหลายบัตรค้าง ให้ขึ้นอันที่หนักสุด MDR > TB > LTBI
     * @return array<int, array{type:int, id:int, label:string, bg:string, fg:string, title:string}>  key = patient_id
     */
    public static function activeMarks($patientIds): array
    {
        $ids = collect($patientIds)->filter()->unique()->values();
        if ($ids->isEmpty()) { return []; }
        $rank = [self::TYPE_MDR => 3, self::TYPE_TB => 2, self::TYPE_LTBI => 1];
        $out = [];
        foreach (self::query()->whereIn('patient_id', $ids)->whereNull('outcome')
                     ->orderByDesc('id')->get(['id', 'patient_id', 'registry_type', 'tb_no']) as $r) {
            $type = (int) $r->registry_type;
            if (!isset(self::MARK[$type])) { continue; }
            $cur = $out[$r->patient_id] ?? null;
            if ($cur && $rank[$cur['type']] >= $rank[$type]) { continue; }
            $out[$r->patient_id] = ['type' => $type, 'id' => (int) $r->id] + self::MARK[$type]
                + ['title' => (self::$typeOption[$type] ?? '') . ' · กำลังรักษา' . ($r->tb_no ? ' · เลขทะเบียน ' . $r->tb_no : '') . ' — กดเพื่อเปิดบัตร'];
        }
        return $out;
    }

    public function typeName(): string
    {
        return self::$typeOption[$this->registry_type] ?? '-';
    }

    public function outcomeName(): string
    {
        return $this->outcome ? (self::outcomeOptionsFor($this->registry_type)[$this->outcome] ?? (self::$outcomeOption[$this->outcome] ?? '-')) : 'กำลังรักษา';
    }

    /** เดือนที่ต้องตรวจเสมหะตามสูตรที่เลือก (ไว้ไฮไลต์ในตาราง) */
    public function requiredSputumMonths(): array
    {
        return self::$sputumMonths[$this->sputum_schedule] ?? [];
    }

    /**
     * รักษามาแล้วกี่เดือน นับจากวันเริ่มกินยา
     * ใช้ตัดสินว่าตารางเสมหะ/ขนาดยาควรแสดงถึงเดือนที่เท่าไหร่
     */
    /**
     * ระยะการรักษา ณ วันที่หนึ่ง ๆ สำหรับลงปฏิทินกินยาจากหน้ารวม DOT
     *
     * ใช้ของเดิมในเดือนนั้นถ้าเคยลงไว้ ไม่งั้นเดาจากเดือนที่รักษา
     * (2 เดือนแรก = ระยะเข้มข้น ตามสูตรมาตรฐาน) พยาบาลแก้ในบัตรได้ทีหลัง
     */
    public function dotPhaseOn(string $date): int
    {
        $same = TbDotDay::where('tb_registration_id', $this->id)
            ->where('dot_date', 'like', substr($date, 0, 7) . '-%')
            ->value('phase');
        if ($same) { return (int) $same; }

        $start = $this->treatment_start_date;
        if (! $start) { return 1; }

        $months = ((int) substr($date, 0, 4) - (int) substr($start, 0, 4)) * 12
                + ((int) substr($date, 5, 2) - (int) substr($start, 5, 2));

        return $months < 2 ? 1 : 2;
    }

    /**
     * ระยะการรักษาจากตัวยา: 1 = เข้มข้น (4FDC / HRZE) · 2 = ต่อเนื่อง (2FDC / HR) · null = บอกไม่ได้ (สูตรดื้อยา ฯลฯ)
     * ใช้ทั้งเลือกตัวเลือกสูตรในกล่องสั่งยา และระยะ DOT ที่มาจากการสั่งยา (คลินิกขอ 2569-10-02)
     */
    public static function phaseOfDrugs(array $drugs, ?string $regimenText = null): ?int
    {
        $d = array_values(array_diff(array_map('strval', $drugs), ['B6']));
        // สูตรเม็ดรวมที่เก็บทั้งสองระยะไว้ด้วยกัน (2HRZE/4HR (FDC) แบบเดิม) บอกระยะไม่ได้
        if (in_array('4FDC', $d, true) && in_array('2FDC', $d, true)) { return null; }
        if (in_array('4FDC', $d, true)) { return 1; }
        if (in_array('2FDC', $d, true)) { return 2; }
        if ($d) {
            $hasHR = in_array('H', $d, true) && in_array('R', $d, true);
            if ($hasHR && (in_array('Z', $d, true) || in_array('E', $d, true))) { return 1; }
            if ($hasHR && ! array_diff($d, ['H', 'R'])) { return 2; }
            return null;
        }
        // ไม่มีรายการยา (สูตรที่บันทึกก่อนมีช่องติ๊กยา) — อ่านจากชื่อสูตร ถ้าเป็นระยะเดียวชัด ๆ
        $t = strtoupper(preg_replace('/\s+/', '', (string) $regimenText));
        if ($t === '' || strpos($t, '/') !== false) { return null; }
        if (preg_match('/^\d*HRZE/', $t)) { return 1; }
        if (preg_match('/^\d*HR(\(|$)/', $t)) { return 2; }
        return null;
    }

    /** วันที่ พ.ศ. Y-m-d · แถวเก่าที่หลุดเป็น ค.ศ. (ปี < 2500) บวก 543 ให้เทียบกันได้ */
    public static function beDate(?string $v): ?string
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})/', (string) $v, $m)) { return null; }
        $y = (int) $m[1];
        return sprintf('%04d-%s-%s', $y < 2500 ? $y + 543 : $y, $m[2], $m[3]);
    }

    /**
     * ระยะการรักษา ณ วันที่หนึ่ง จาก "การสั่งยา" — ช่วงสูตรยาที่ใช้อยู่วันนั้น แล้วค่อยรอบสั่งยาล่าสุดก่อนวันนั้น
     * คืน null ถ้าการสั่งยาบอกไม่ได้ (ให้ผู้เรียกใช้ dotPhaseOn แบบเดิม)
     * ส่ง $changes / $doses ที่โหลดไว้แล้วมาได้ (ปฏิทินทั้งเดือนจะได้ไม่ยิงฐานทีละวัน)
     */
    public function orderPhaseOn(string $date, $changes = null, $doses = null): ?int
    {
        $changes = $changes ?? TbRegimenChange::where('tb_registration_id', $this->id)->get();
        // ช่วงที่คลุมวันนั้น — วันรอยต่อ (วันจบของช่วงเก่า = วันเริ่มของช่วงใหม่) ให้ช่วงใหม่ชนะ
        $hit = $changes->filter(function ($c) use ($date) {
            $s = self::beDate($c->start_date);
            $e = self::beDate($c->end_date);
            return (! $s || $s <= $date) && (! $e || $e >= $date) && ($s || $e);
        })->sortBy(fn ($c) => [self::beDate($c->start_date) ?? '', $c->id])->last();
        if ($hit) {
            $drugs = (array) ($hit->drugs ?? []);
            if (! $drugs) {
                $match = TbRegimen::where('active', true)
                    ->where(fn ($q) => $q->where('regimen', $hit->regimen)->orWhere('name', $hit->regimen))
                    ->orderByRaw('drugs IS NULL')->first();
                $drugs = $match && $match->drugs ? array_keys($match->drugs) : [];
            }
            $p = self::phaseOfDrugs($drugs, $hit->regimen);
            if ($p) { return $p; }
        }

        // รอบสั่งยาล่าสุดที่จ่ายก่อน/ในวันนั้น
        $doses = $doses ?? TbMonthlyDose::where('tb_registration_id', $this->id)->whereNotNull('doses')->get();
        $row = $doses->filter(fn ($r) => ($d = self::beDate($r->dispensed_date)) && $d <= $date && $r->doses)
            ->sortBy(fn ($r) => [self::beDate($r->dispensed_date), (int) $r->sort, $r->id])->last();
        if ($row) {
            $keys = array_keys(array_filter((array) $row->doses, fn ($v) => trim((string) $v) !== ''));
            return self::phaseOfDrugs($keys);
        }

        return null;
    }

    /** ระยะ DOT ที่ใช้บันทึกจริง — ตามการสั่งยาก่อน ถ้าบอกไม่ได้ใช้กติกาเดิม (ระยะของเดือน / 2 เดือนแรก) */
    public function dotPhaseFor(string $date): int
    {
        return $this->orderPhaseOn($date) ?? $this->dotPhaseOn($date);
    }

    public function isWeeklyDot(): bool
    {
        return (int) $this->dot_frequency === self::DOT_WEEKLY;
    }

    /** ข้อความกำหนดการ เช่น "ทุกวัน 7.30 น." หรือ "วันศุกร์ 20.00 น." */
    public function dotScheduleText(): string
    {
        $when = $this->isWeeklyDot()
            ? 'วัน' . (self::$weekdayOption[(int) $this->dot_weekday] ?? '—')
            : 'ทุกวัน';

        return trim($when . ' ' . (string) $this->dot_time);
    }

    /**
     * สูตรยาแบบรายเม็ดสำหรับตาราง DOT — ใช้เติมให้ครั้งแรก พยาบาลแก้ต่อเองได้
     *
     * ประกอบจากยาในสูตรที่ใช้อยู่ + ความแรงในรายการยา + จำนวนเม็ดของสูตรมาตรฐาน
     * เช่น Rifampicin (R) 450 mg * 1  — ส่วนรายละเอียดอย่าง "จ,พ,ศ" พยาบาลเติมเอง
     */
    /**
     * แปลงขนาดยาในตารางขนาดยารายเดือน (หมอกรอกเป็น มก. หรือ "n เม็ด") เป็นจำนวนเม็ดต่อวัน/ต่อครั้ง
     * ใช้ร่วมกันทั้งข้อความสูตรยาหน้า DOT และสติกเกอร์ยา (รีวิว 2569-10-01)
     *
     * คืน ['kind' => tab|mg|inj|raw, 'tabs' => เม็ด, 'strength' => มก./เม็ด, 'mg' => มก.]
     *  - "4 เม็ด" / ยาเม็ดรวม (FDC ไม่มีความแรงในรายการ) ตัวเลขน้อย ๆ = จำนวนเม็ด
     *  - มก. + มีความแรงเม็ด = เลือกความแรงที่หารลงตัวและได้เม็ดน้อยสุด
     *    INH วัณโรค = เม็ด 100 มก. · วัณโรคแฝง = เม็ด 300 มก. (คลินิกแจ้ง 2569-09-30) ถ้าหารลงตัว
     *  - ยาฉีด (ความแรงเป็น /ml หรือ g) = บอกเป็น มก. inj
     *  - หาความแรงไม่เจอ = บอกเป็น มก. ไม่เดาว่าเป็นเม็ด
     */
    /**
     * ความแรงต่อเม็ดของยาเม็ดเดี่ยวในรายการยา TB — ใช้ทั้งแปลง มก. -> เม็ด และช่อง "ความแรง" ในกล่องสั่งยา
     * คืน ['inj' => ยาฉีด?, 'strengths' => [มก. เรียงน้อย->มาก], 'prefer' => ความแรงที่คลินิกใช้ก่อน|null]
     */
    public function tabletStrengths(string $key): array
    {
        static $catalog = null;
        $catalog = $catalog ?? TbDrug::where('active', true)->get();

        // รหัสในตารางขนาดยา -> รหัสในรายการยา (สูตร TPT ใช้ชื่อ H-3HP / P-1HP)
        $alias = ['H' => ['h', 'inh'], 'INH' => ['h', 'inh'], 'H-3HP' => ['h', 'inh'], 'H-1HP' => ['h', 'inh'], 'P-1HP' => ['p']];
        $codes = $alias[$key] ?? [strtolower($key)];
        $hits  = $catalog->filter(fn ($d) => preg_match('/\(([^)]+)\)/', (string) $d->name, $m)
            && in_array(strtolower(rtrim($m[1], '.')), $codes, true));

        $inj = $hits->contains(fn ($d) => preg_match('/ml|\d\s*g\b/i', (string) $d->dose));
        $strengths = $inj ? [] : $hits->map(fn ($d) => preg_match('/^\s*(\d+(?:\.\d+)?)\s*mg/i', (string) $d->dose, $m) ? (float) $m[1] : null)
            ->filter()->unique()->sort()->values()->all();

        $isInh  = in_array($key, ['H', 'INH', 'H-3HP', 'H-1HP'], true);
        $prefer = $isInh ? ((int) $this->registry_type === self::TYPE_LTBI || $key === 'H-3HP' ? 300.0 : 100.0) : null;

        return ['inj' => $inj, 'strengths' => $strengths, 'prefer' => $prefer, 'found' => $hits->isNotEmpty()];
    }

    public function doseTablets(string $key, string $amount): array
    {
        static $catalog = null;
        $catalog = $catalog ?? TbDrug::where('active', true)->get();
        $out = ['kind' => 'raw', 'tabs' => null, 'strength' => null, 'mg' => null];

        $num = preg_match('/\d+(\.\d+)?/', $amount, $n) ? (float) $n[0] : null;
        if (! $num) { return $out; }
        // B6 เม็ดเดียว 50 มก. ไม่มีในรายการยา TB — ตารางขนาดยากรอกเป็น "เม็ด/วัน" (คลินิกขอ 2569-10-02)
        // ค่าเก่าที่กรอกเป็น มก. (50) ยังอ่านถูก: ตัวเลขเกิน 10 ถือเป็น มก. แล้วหารด้วย 50
        if ($key === 'B6') {
            $tabs = preg_match('/เม็ด|tab/iu', $amount) || $num <= 10 ? $num : $num / 50;
            return ['kind' => 'tab', 'tabs' => $tabs, 'strength' => 50.0, 'mg' => $tabs * 50];
        }
        if (preg_match('/เม็ด|tab/iu', $amount)) {
            return ['kind' => 'tab', 'tabs' => $num, 'strength' => null, 'mg' => null];
        }

        // รหัสในตารางขนาดยา -> รหัสในรายการยา (สูตร TPT ใช้ชื่อ H-3HP / P-1HP)
        $alias = ['H' => ['h', 'inh'], 'INH' => ['h', 'inh'], 'H-3HP' => ['h', 'inh'], 'H-1HP' => ['h', 'inh'], 'P-1HP' => ['p']];
        $codes = $alias[$key] ?? [strtolower($key)];
        $hits  = $catalog->filter(fn ($d) => preg_match('/\(([^)]+)\)/', (string) $d->name, $m)
            && in_array(strtolower(rtrim($m[1], '.')), $codes, true));

        // ยาฉีด: ความแรงเป็นต่อ ml หรือเป็นกรัม (Am 500 mg/2 ml, Km 1 g)
        if ($hits->contains(fn ($d) => preg_match('/ml|\d\s*g\b/i', (string) $d->dose))) {
            return ['kind' => 'inj', 'tabs' => null, 'strength' => null, 'mg' => $num];
        }
        $strengths = $hits->map(fn ($d) => preg_match('/^\s*(\d+(?:\.\d+)?)\s*mg/i', (string) $d->dose, $m) ? (float) $m[1] : null)
            ->filter()->unique()->sort()->values();

        if ($strengths->isEmpty()) {
            // ยาเม็ดรวม (4FDC/2FDC) ไม่มีความแรงในรายการ — ตัวเลขน้อยคือจำนวนเม็ด · ตัวเลขมากคือ มก.
            return $num <= 10 && ($hits->isNotEmpty() || preg_match('/fdc/i', $key))
                ? ['kind' => 'tab', 'tabs' => $num, 'strength' => null, 'mg' => null]
                : ['kind' => 'mg', 'tabs' => null, 'strength' => null, 'mg' => $num];
        }

        $isInh  = in_array($key, ['H', 'INH', 'H-3HP', 'H-1HP'], true);
        $prefer = $isInh ? ((int) $this->registry_type === self::TYPE_LTBI || $key === 'H-3HP' ? 300.0 : 100.0) : null;
        $even   = fn ($s) => abs($num / $s - round($num / $s)) < 1e-9;
        // ความแรงที่หมอเลือกในกล่อง "สั่งยาครั้งนี้" (เก็บไว้ในวิธีใช้) มาก่อน — สติกเกอร์/ใบกินยา/หน้า DOT
        // จะได้บอกเม็ดเดียวกับที่สั่ง ไม่ใช่เดาความแรงใหม่เอง (เช่น สั่ง R 450 × 2 แต่สติกเกอร์ขึ้น 300 × 3)
        $hint   = (float) ($this->sigFor($key)['strength'] ?? 0);
        $pick   = ($hint && $strengths->contains($hint) && $even($hint)) ? $hint
            : (($prefer && $strengths->contains($prefer) && $even($prefer))
                ? $prefer : $strengths->sortDesc()->first($even));
        // ไม่มีความแรงไหนหารลงตัว (เช่น R 800 มก. มีแต่เม็ด 300/450) — บอกเป็น มก. ไม่ออกเศษเม็ด 2.67
        if (! $pick) {
            return ['kind' => 'mg', 'tabs' => null, 'strength' => null, 'mg' => $num];
        }

        return ['kind' => 'tab', 'tabs' => $num / $pick, 'strength' => $pick, 'mg' => $num];
    }

    /**
     * สูตรยาแบบที่พยาบาลเขียนในหน้า DOT จาก "การสั่งยาครั้งล่าสุดของหมอ" (พยาบาลขอ 2569-09-30)
     * เช่น "INH 100 mg. 3 tab OD" — คืน ['text' => ..., 'month' => เดือนที่] หรือ null ถ้ายังไม่มีการสั่งยา
     *
     * การสั่งยาของคลินิกวัณโรค = ตารางขนาดยารายเดือนในบัตร TB 01 (หมอกรอกเป็น มก./วัน)
     * แปลง มก. เป็นจำนวนเม็ดด้วยความแรงในรายการยา: เลือกความแรงที่หารลงตัวและได้เม็ดน้อยสุด
     * (E 1000 มก. -> 500 mg 2 tab) ถ้าไม่มีตัวไหนหารลงตัว ใช้ความแรงต่ำสุดแล้วบอกเป็นทศนิยม
     * ความถี่มาจากกล่อง "วิธีใช้ยา" ของบัตร ถ้ายังไม่ได้ตั้งถือว่า OD
     */
    /** โหลดตารางขนาดยาเดือนล่าสุดของหลายบัตรในคำสั่งเดียว ให้ latestOrderDotText() ใช้ */
    public static function preloadLatestDoses($regs): void
    {
        $latest = TbMonthlyDose::whereIn('tb_registration_id', collect($regs)->pluck('id'))
            ->whereNotNull('doses')->orderByDesc('sort')->orderByDesc('id')->get()
            ->groupBy('tb_registration_id')->map->first();
        foreach ($regs as $r) { $r->setRelation('latestDose', $latest[$r->id] ?? null); }
    }

    public function latestOrderDotText(): ?array
    {
        // บอร์ด DOT โหลดของทุกแถวไว้ทีเดียว (preloadLatestDoses) — ไม่ต้องยิงทีละคน
        $dose = $this->relationLoaded('latestDose') ? $this->getRelation('latestDose')
            : TbMonthlyDose::where('tb_registration_id', $this->id)
                ->whereNotNull('doses')->orderByDesc('sort')->orderByDesc('id')->first();
        if (! $dose || ! $dose->doses) { return null; }

        $fmt     = fn ($n) => rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
        $lines   = [];

        // เรียง H R Z E ก่อนตามที่คลินิกเขียน แล้วตามด้วยยาอื่น B6 ไว้ท้ายสุด
        $doses = (array) $dose->doses;
        $rank  = array_flip(array_merge(TbDrug::$drugOrder, ['INH']));
        $rank['INH'] = $rank['H'];
        uksort($doses, function ($a, $b) use ($rank) {
            $ra = $a === 'B6' ? 999 : ($rank[$a] ?? 100);
            $rb = $b === 'B6' ? 999 : ($rank[$b] ?? 100);
            return $ra <=> $rb ?: strcmp($a, $b);
        });

        foreach ($doses as $key => $amount) {
            $amount = trim((string) $amount);
            if ($amount === '') { continue; }

            $dt    = $this->doseTablets($key, $amount);
            $sig   = $this->sigFor($key);
            $code  = $sig['freq'] ?? '';
            $freq  = in_array($code, ['QW', 'TIW'], true)
                ? '(' . (trim((string) ($sig['note'] ?? '')) ?: (self::$sigFreq[$code][0] ?? '')) . ')'
                : ($code ?: 'OD');
            $name  = $key === 'H' ? 'INH' : $key;

            if ($dt['kind'] === 'tab' && $dt['strength']) {
                $lines[] = $name . ' ' . $fmt($dt['strength']) . ' mg. ' . $fmt($dt['tabs']) . ' tab ' . $freq;
            } elseif ($dt['kind'] === 'tab') {
                $lines[] = $name . ' ' . $fmt($dt['tabs']) . ' tab ' . $freq;
            } elseif ($dt['kind'] === 'inj') {
                $lines[] = $name . ' ' . $fmt($dt['mg']) . ' mg. inj ' . $freq;
            } elseif ($dt['kind'] === 'mg') {
                // หาความแรงเม็ดไม่เจอ — บอกเป็น มก. ตรง ๆ ไม่เดาเป็นจำนวนเม็ด (รีวิว 2569-10-01: เคยพิมพ์ "H-3HP 900 tab")
                $lines[] = $name . ' ' . $fmt($dt['mg']) . ' mg. ' . $freq;
            } else {
                $lines[] = trim($name . ' ' . $amount . ' ' . $freq);
            }
        }

        return $lines ? ['text' => implode("\n", $lines), 'month' => (int) $dose->month_no, 'label' => $dose->labelForHumans()] : null;
    }

    public function suggestedDotRegimenText(): string
    {
        $keys = $this->doseDrugKeys();
        if (! $keys) { return ''; }

        $text  = trim((string) $this->currentRegimenText());
        $match = TbRegimen::where('active', true)
            ->where(function ($q) use ($text) { $q->where('regimen', $text)->orWhere('name', $text); })
            ->orderByRaw('drugs IS NULL')->first();
        $pills = (array) ($match->drugs ?? []);

        $drugs = TbDrug::where('active', true)->get();
        $lines = [];

        foreach ($keys as $key) {
            // ยาตัวเดียวกันมีหลายความแรงในรายการ — เอาแถวที่ระบุความแรงไว้ก่อน
            $hits = $drugs->filter(function ($d) use ($key) {
                return preg_match('/\(([^)]+)\)/', (string) $d->name, $m) && strcasecmp(rtrim($m[1], '.'), $key) === 0;
            });
            $drug = $hits->first(fn ($d) => $d->dose) ?: $hits->first();

            $dose  = $drug->dose ?? null;
            $count = $pills[$key] ?? null;

            $line = $key . ($dose ? '(' . preg_replace('/\s*mg\.?$/i', '', trim($dose)) . ')' : '')
                  . ($count ? '*' . $count : '');
            if ($drug && $drug->note) { $line .= ' — ' . $drug->note; }

            $lines[] = trim($line);
        }

        return implode("\n", $lines);
    }

    /** พยาบาลเช็ค DOT ของวันหนึ่งได้ถึง 16:00 ของวันทำการถัดไป — เลยแล้วยังไม่ลง = ไม่ได้บันทึก (คลินิกกำหนด 2569-10-01) */
    public const DOT_CHECK_CUTOFF = '16:00';

    /** เวลากินยาเป็นนาทีนับจากเที่ยงคืน — ช่องนี้เคยพิมพ์อิสระ ("7.30 น.", "18:00") อ่านไม่ออก = null */
    public function dotTimeMinutes(): ?int
    {
        $t = trim((string) $this->dot_time);
        if (preg_match('/(\d{1,2})\s*[.:]\s*(\d{2})/', $t, $m) || preg_match('/^(\d{1,2})(?:\s*น\.?)?$/u', $t, $m)) {
            $h = (int) $m[1]; $i = (int) ($m[2] ?? 0);
            return ($h <= 23 && $i <= 59) ? $h * 60 + $i : null;
        }
        if (preg_match('/เย็น|ก่อนนอน|ค่ำ/u', $t)) { return 18 * 60; }
        return null;
    }

    /** เวลากินยาในรูป HH:MM สำหรับช่องเลือกเวลา (ค่าเดิมที่พิมพ์ไว้แปลงให้) */
    public function dotTimeHm(): string
    {
        $m = $this->dotTimeMinutes();
        return $m === null ? '' : sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
    }

    /**
     * สถานะของ "วันที่ยังไม่มีใครลง DOT" — ใช้ร่วมกันทั้งหน้า DOT และปฏิทินในบัตร TB 01 (คลินิกกำหนด 2569-10-01)
     *
     *   null    = วันนั้นไม่ต้องลง (ยังไม่ถึง / ก่อนเริ่มลง DOT ในระบบ / ไม่ได้ DOT / รายสัปดาห์ที่ไม่ใช่วันนัด)
     *   self    = เสาร์-อาทิตย์/วันหยุดราชการ: คนไข้กินยาเอง พยาบาลมาตรวจสอบวันทำการแล้วแก้ย้อนหลังได้ — ไม่ขึ้นกากบาท
     *   pending = รอเช็ค: เวลากินยาหลัง 16:00 พยาบาลเช็คได้วันทำการถัดไป
     *   open    = ปกติ: เวลากินยาในเวลาทำการ ยังเช็คทัน
     *   missed  = เลย 16:00 ของวันทำการถัดไปแล้วยังไม่มีใครลง = "ไม่ได้บันทึก" (กากบาท)
     *
     * เป็นสถานะสำหรับแสดงเท่านั้น ไม่บันทึกลงฐาน — 0 "ไม่กินยา" ต้องให้พยาบาลยืนยันเอง ไม่งั้นรายงานขาดยาเพี้ยน
     */
    public function dotUnrecordedState(string $dateBe, ?string $dotSince, ?\Carbon\Carbon $now = null): ?string
    {
        $now   = $now ?? now();
        $today = ($now->year + 543) . $now->format('-m-d');
        if (! $dotSince || $dateBe < $dotSince || $dateBe > $today) { return null; }
        if (! ($this->dot_active || ($this->dot_off_date && $dateBe <= $this->dot_off_date))) { return null; }

        $ad = \Carbon\Carbon::parse(Utillity::BE2AD($dateBe))->startOfDay();
        if ($this->isWeeklyDot() && (int) $ad->format('N') !== (int) $this->dot_weekday) { return null; }
        if (Holiday::isClinicClosed($ad)) { return 'self'; }

        // เส้นตาย = 16:00 ของวันทำการถัดไป (ข้ามเสาร์-อาทิตย์/วันหยุด)
        $next = $ad->copy()->addDay();
        for ($i = 0; $i < 30 && Holiday::isClinicClosed($next); $i++) { $next->addDay(); }
        [$h, $m] = array_map('intval', explode(':', self::DOT_CHECK_CUTOFF));
        if ($now->gte($next->copy()->setTime($h, $m))) { return 'missed'; }

        $dose = $this->dotTimeMinutes();
        return ($dose !== null && $dose > $h * 60 + $m) ? 'pending' : 'open';
    }

    /** วันที่ต้องเช็คให้เสร็จ (ใช้บอกใต้ "รอเช็ค") — 16:00 ของวันทำการถัดไป เช่น "ถึง 16:00 จ. 5/10" */
    public static function dotCheckDeadlineText(string $dateBe): string
    {
        $next = \Carbon\Carbon::parse(Utillity::BE2AD($dateBe))->addDay();
        for ($i = 0; $i < 30 && Holiday::isClinicClosed($next); $i++) { $next->addDay(); }
        $wd = ['อา.', 'จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.'][(int) $next->format('w')];
        return 'ถึง ' . self::DOT_CHECK_CUTOFF . ' ' . $wd . ' ' . $next->format('j/n');
    }

    /** วันเริ่มนับเดือนที่ (พ.ศ.) — เปลี่ยนไปกลุ่มสูตรอื่นกลางคอร์ส (เช่น 2HRZE → BPaL) นับจากวันเริ่มสูตรนั้น ตรงกับ ด.0 ของตารางติดตาม */
    public function countStartDate(): ?string
    {
        $k = (string) $this->treatment_start_date . '|' . (string) $this->regimen;
        if (! array_key_exists($k, $this->countStartMemo)) {
            try { $v = \App\Services\TbMonitor\TbMonitor::countStartBe($this); } catch (\Throwable $e) { $v = $this->treatment_start_date; }
            $this->countStartMemo = [$k => $v];
        }
        return $this->countStartMemo[$k];
    }

    /** นับเดือนจากวันเริ่มสูตรกลุ่มปัจจุบัน ไม่ใช่วันเปิดคอร์สแรก (ดู countStartDate) */
    public function monthsOnTreatment(): int
    {
        $s = $this->countStartDate();
        $start = $s ? Utillity::BE2AD($s) : null;
        if (! $start) { return 0; }

        try {
            $from = \Carbon\Carbon::createFromFormat('Y-m-d', $start);
        } catch (\Throwable $e) {
            return 0;
        }

        return max(0, (int) $from->diffInMonths(now(), true));
    }

    /**
     * วันที่ให้คำปรึกษา HIV / วันที่ส่งตรวจเลือด HIV จากการมา OPD คลินิกวัณโรค "ครั้งแรก" ของคนไข้ (คลินิกขอ 2569-10-12)
     * ช่องในบัตร TB 01 เดิมไม่ผูกกับหน้าตรวจ พยาบาลต้องพิมพ์ซ้ำ
     * - counsel = visit TB แรกที่ลง Pre-test (diagnoses.disease 13)
     * - blood   = visit TB แรกที่สั่ง Anti-HIV (lab_subs.method 6)
     * คืนวันที่ visit (พ.ศ. Y-m-d) หรือ null · ไม่ดูคลินิกอื่น (ตรวจ HIV ที่ STI เมื่อหลายปีก่อนไม่ใช่การให้คำปรึกษาตอนเริ่มรักษา TB)
     */
    public static function opdHivDates(?int $patientId): array
    {
        $out = ['counsel' => null, 'blood' => null];
        if (! $patientId) { return $out; }
        $norm = function ($d) {
            if (! $d || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})/', (string) $d, $m)) { return null; }
            $y = (int) $m[1]; if ($y < 2500) { $y += 543; }   // ข้อมูลยุคแรกบางแถวเป็น ค.ศ.
            return sprintf('%04d-%s-%s', $y, $m[2], $m[3]);
        };
        $out['counsel'] = $norm(\Illuminate\Support\Facades\DB::table('diagnoses')
            ->join('visits', 'visits.id', '=', 'diagnoses.visit_id')
            ->where('visits.patient_id', $patientId)->where('visits.clinic', 2)->where('diagnoses.disease', 13)
            ->min('visits.date'));
        $out['blood'] = $norm(\Illuminate\Support\Facades\DB::table('lab_subs')
            ->join('labs', 'labs.id', '=', 'lab_subs.lab_id')
            ->join('visits', 'visits.id', '=', 'labs.visit_id')
            ->where('visits.patient_id', $patientId)->where('visits.clinic', 2)->where('lab_subs.method', 6)
            ->min('visits.date'));
        return $out;
    }
}
