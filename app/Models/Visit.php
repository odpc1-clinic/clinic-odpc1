<?php

namespace App\Models;

use App\Utillity;
use Illuminate\Database\Eloquent\Model;

class Visit extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'visits';

    /**
     * The database primary key value.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'date', 'patient_id', 'know_from', 'send_from', 'other_from',
        'reason_sti', 'reason_sti_other', 'risk_behavior', 'other_risk_behavior',
        'reason_vct', 'reason_vct_other', 'sti_hostory', 'contraceptive_method',
        'LMP', 'lmp_days', 'symptom', 'onset_date', 'body_check', 'treatment', 'consultation',
        'hiv_sti_test', 'hiv_sti_test_date', 'hiv_sti_test_resule', 'syphilis_history',
        'touch_tracing', 'touch_tracing_fail', 'provide_condom_site',
        'provide_condom_quantity', 'provide_lubricant_quantity', 'appointment',
        'appointment_reason', 'appointment_staff_note', 'contraception', 'clinic', 'status','room_entered_at','queue','last_hiv_syphilis','reminder_sent_at','appointment_confirm_sent_at',
        'trush_tracks', 'body_check_marks','registration_by', 'registration_at', // เพิ่ม
        'nurse_by', 'screening_at',           // เพิ่ม
        'doctor_by', 'doctor_at',             // เพิ่ม
        'lab_by',                             // lab มีตารางแยกอยู่แล้ว อาจไม่ต้องเพิ่มที่นี่
        'pharmacy_by', 'pharmacy_at',         // เพิ่ม
        'handled_by', 'finished_at',          // เพิ่ม
        // สัญญาณชีพ — ห้องซักประวัติ TMC (migration 2026_07_21_180000)
        'weight', 'height', 'temperature', 'pulse',
        'systolic', 'diastolic', 'respiratory_rate', 'spo2',
        'dtx', 'qtcf',                        // คลินิกวัณโรค (migration 2026_10_22_120000)
        // ซักประวัติเดินทาง TMC (migration 2026_07_24_160000)
        'tmc_reason', 'tmc_travel_phase', 'tmc_menopause', 'tmc_reason_malaria', 'tmc_reason_altitude', 'tmc_reason_vaccine', 'tmc_vaccine_specify',
        'tmc_pregnant', 'tmc_pregnant_detail', 'tmc_breastfeeding',
        'tmc_destinations', 'tmc_dest_countries', 'tmc_depart_date', 'tmc_return_date',
        'tmc_area_rural', 'tmc_area_urban', 'tmc_area_altitude', 'tmc_area_forest', 'tmc_area_diving', 'tmc_travel_style', 'refer_hospital', 'tmc_leave_time',
        'tmc_trip_purpose', 'tmc_activities', 'tmc_animal_exposure', 'tmc_accommodation',
        'tmc_risk_assessment', 'tmc_plan', 'tmc_yellow_book_no',
        // แผนนัดฉีดวัคซีนที่หมอวางล่วงหน้า (JSON, migration 2026_07_27_100000)
        'tmc_vaccine_plan', 'tmc_pe',
        // ส่วนสุภาพสตรี TMC (migration 2026_10_07_110000 — ยังไม่ขึ้น = cleanTmcFemale ตัดทิ้งก่อนเขียน)
        'tmc_lmp_end', 'tmc_contraception', 'tmc_contraception_other', 'tmc_last_sex_date',
    ];

    /** จุดประสงค์การเดินทาง (TMC ขอ 2569-10-07) — เป็นตัวเลือกช่วยกรอก ค่าที่พิมพ์เองยังเก็บได้ (คอลัมน์เป็นข้อความ) */
    public static $tmcTripPurposeOption = [
        'ฮัจย์', 'อุมเราะห์', 'นักเรียน/นักศึกษา', 'ท่องเที่ยว', 'เยี่ยมญาติ', 'ทำงาน', 'ธุรกิจ',
        'ประชุม/อบรม/ดูงาน', 'แข่งกีฬา', 'แสวงบุญ', 'เยี่ยมครอบครัว', 'เชิงสุขภาพ/รักษาพยาบาล',
    ];

    /** มีคอลัมน์ tmc_leave_time แล้วหรือยัง (migration 2026_10_09_110000) — ยังไม่มี = ไม่แสดงช่องเวลากลับจริง */
    public static function leaveTimeReady(): bool
    {
        static $ready = null;
        return $ready ??= \Illuminate\Support\Facades\Schema::hasColumn('visits', 'tmc_leave_time');
    }

    /** มีคอลัมน์ refer_hospital แล้วหรือยัง (migration 2026_10_08_100000) — ยังไม่มี = ไม่แสดงตัวเลือก Refer */
    public static function referReady(): bool
    {
        static $ready = null;
        return $ready ??= \Illuminate\Support\Facades\Schema::hasColumn('visits', 'refer_hospital');
    }

    /** มีคอลัมน์สมุดเหลืองครั้งนี้แล้วหรือยัง (migration 2026_11_04_100000) — ยังไม่มี = ใช้กล่องสมุดเหลืองแบบเดิม */
    public static function bookReady(): bool
    {
        static $ready = null;
        return $ready ??= \Illuminate\Support\Facades\Schema::hasColumn('visits', 'tmc_book_action');
    }

    /** มีคอลัมน์ "เล่มที่เขียนเสียครั้งนี้" (หลายเล่ม) แล้วหรือยัง (migration 2026_11_06_100000) */
    public static function bookSpoiledReady(): bool
    {
        static $ready = null;
        return $ready ??= \Illuminate\Support\Facades\Schema::hasColumn('visits', 'tmc_book_spoiled_nos');
    }

    /** มีคอลัมน์ประเทศปลายทางแบบเลือกแล้วหรือยัง (migration 2026_11_04_120000) — ยังไม่มี = ช่องพิมพ์อย่างเดียวแบบเดิม */
    public static function destCountriesReady(): bool
    {
        static $ready = null;
        return $ready ??= \Illuminate\Support\Facades\Schema::hasColumn('visits', 'tmc_dest_countries');
    }

    /**
     * ชื่อสั้นที่คนเรียกจริง — db_country ใช้ชื่อทางการ (รัฐพหุชนชาติแห่งโบลิเวีย) ยาวจนอ่านในช่องเลือกไม่ไหว
     * และมีชื่ออังกฤษเพี้ยนจากการแปลงรหัสอักษร 3 ตัว · ตาราง db_country ใช้ร่วมกับสัญชาติคนไข้ จึงไม่แก้ที่ตาราง
     */
    private const DEST_SHORT = [
        'BOL' => ['โบลิเวีย', 'Bolivia'], 'LAO' => ['ลาว', 'Laos'], 'TZA' => ['แทนซาเนีย', 'Tanzania'],
        'ZAF' => ['แอฟริกาใต้', 'South Africa'], 'USA' => ['สหรัฐอเมริกา', 'USA'], 'KOR' => ['เกาหลีใต้', 'South Korea'],
        'PRK' => ['เกาหลีเหนือ', 'North Korea'], 'VNM' => ['เวียดนาม', 'Vietnam'], 'ARG' => ['อาร์เจนตินา', 'Argentina'],
        'RUS' => ['รัสเซีย', 'Russia'], 'NPL' => ['เนปาล', 'Nepal'], 'ARE' => ['สหรัฐอาหรับเอมิเรตส์', 'UAE'],
        'COD' => ['คองโก (DR Congo)', 'DR Congo'], 'MDA' => ['มอลโดวา', 'Moldova'], 'MKD' => ['มาซิโดเนียเหนือ', 'North Macedonia'],
        'MWI' => ['มาลาวี', 'Malawi'], 'SYR' => ['ซีเรีย', 'Syria'], 'IRN' => ['อิหร่าน', 'Iran'], 'CAF' => ['แอฟริกากลาง', 'Central African Republic'],
        'VEN' => ['เวเนซุเอลา', 'Venezuela'], 'POL' => ['โปแลนด์', 'Poland'], 'KOS' => ['คอซอวอ', 'Kosovo'],
        'BRN' => ['บรูไน', 'Brunei'], 'TWN' => ['ไต้หวัน', 'Taiwan'], 'VAT' => ['วาติกัน', 'Vatican City'],
        'ALA' => ['หมู่เกาะโอลันด์', 'Åland Islands'], 'CIV' => ['โกตดิวัวร์', "Côte d'Ivoire"], 'REU' => ['เรอูนียง', 'Réunion'],
        'GBR' => ['สหราชอาณาจักร', 'United Kingdom'], 'SWZ' => ['เอสวาตินี (สวาซิแลนด์)', 'Eswatini'],
    ];

    /** คำที่คนไข้/พยาบาลพิมพ์กัน ใช้ค้นในช่องเลือก และเดาประเทศจากข้อความเก่า */
    private const DEST_ALIAS = [
        'USA' => ['อเมริกา', 'US', 'America', 'United States'], 'GBR' => ['อังกฤษ', 'UK', 'England', 'Britain', 'สก็อตแลนด์', 'Scotland'],
        'SAU' => ['ซาอุ', 'ซาอุดิอาราเบีย', 'Saudi'], 'KOR' => ['เกาหลี', 'Korea'], 'NLD' => ['ฮอลแลนด์', 'Holland'],
        'ETH' => ['Ethipia'], 'MMR' => ['พม่า', 'Burma'], 'KHM' => ['เขมร'],
        'ARE' => ['ดูไบ', 'Dubai'], 'COD' => ['Congo'], 'CZE' => ['เช็ก', 'Czech'],
        'ARG' => ['อาเจนติน่า', 'อาร์เจนติน่า'], 'EGY' => ['Egyp'], 'SWZ' => ['Esvatini', 'Swaziland', 'สวาซิแลนด์'],
    ];

    /** รายการประเทศให้เลือก [code => ['th','en','label','search']] เรียงตามชื่อไทย */
    public static function destCountryList(): array
    {
        static $list = null;
        if ($list !== null) { return $list; }
        $list = [];
        foreach (\App\Models\Country::query()->whereNotNull('country_id')->get(['country_id', 'country_name_th', 'country_name_en']) as $c) {
            $code = strtoupper(trim((string) $c->country_id));
            if ($code === '') { continue; }
            [$th, $en] = self::DEST_SHORT[$code] ?? [trim((string) $c->country_name_th), trim((string) $c->country_name_en)];
            $list[$code] = [
                'th' => $th, 'en' => $en, 'label' => $th . ' (' . $en . ')',
                'search' => implode(' ', array_merge([$th, $en, $code, trim((string) $c->country_name_th), trim((string) $c->country_name_en)], self::DEST_ALIAS[$code] ?? [])),
            ];
        }
        uasort($list, fn ($a, $b) => strcmp($a['th'], $b['th']));
        return $list;
    }

    /** ทำความสะอาดค่าจากฟอร์ม "KEN,TZA" — ตัดรหัสที่ไม่มีในรายการ ซ้ำ และช่องว่าง · ว่าง = null */
    public static function cleanDestCountries($raw): ?string
    {
        $list = self::destCountryList();
        $codes = is_array($raw) ? $raw : explode(',', (string) $raw);
        $out = [];
        foreach ($codes as $c) {
            $c = strtoupper(trim((string) $c));
            if ($c !== '' && isset($list[$c]) && !in_array($c, $out, true)) { $out[] = $c; }
        }
        return $out ? implode(',', $out) : null;
    }

    /** "KEN,TZA" -> ['KEN' => 'เคนยา', 'TZA' => 'แทนซาเนีย'] */
    public static function destCountryNames(?string $codes): array
    {
        $list = self::destCountryList();
        $out = [];
        foreach (array_filter(explode(',', (string) $codes)) as $c) {
            $out[$c] = $list[$c]['th'] ?? $c;
        }
        return $out;
    }

    /**
     * เดาประเทศจากข้อความที่พิมพ์ไว้เดิม (ใช้เติมย้อนหลัง) — จับชื่อไทย/อังกฤษ/ชื่อเล่น
     * คำยาวก่อนคำสั้น แล้วลบคำที่จับได้ออก (แอฟริกาใต้ จะไม่โดนจับซ้ำเป็นอย่างอื่น)
     * ภูมิภาค (อเมริกากลาง, central africa) / "ทั่วโลก" ไม่ใช่ประเทศเดียว จึงไม่เดา ปล่อยให้พยาบาลเลือกเอง
     */
    public static function guessDestCountries(?string $text): array
    {
        // ตัดพินทุ/อักษรล่องหนที่หลุดมาหน้าคำอังกฤษ (เช่น "ฺBrazil")
        $hay = ' ' . mb_strtolower(preg_replace('/[\x{0E3A}\x{200B}]/u', '', (string) $text)) . ' ';
        foreach (['อเมริกากลาง', 'อเมริกาใต้', 'central america', 'south america', 'latin america', 'ทั่วโลก', 'แอฟริกากลาง', 'central africa'] as $region) {
            $hay = str_replace($region, ' ', $hay);
        }
        $terms = [];
        foreach (self::destCountryList() as $code => $c) {
            foreach (array_merge([$c['th'], $c['en']], self::DEST_ALIAS[$code] ?? []) as $t) {
                $t = mb_strtolower(trim(preg_replace('/\s*\(.*\)$/u', '', $t)));
                if (mb_strlen($t) >= 2) { $terms[] = [$t, $code]; }
            }
        }
        usort($terms, fn ($a, $b) => mb_strlen($b[0]) <=> mb_strlen($a[0]));
        $found = [];
        foreach ($terms as [$t, $code]) {
            // อังกฤษต้องเป็นทั้งคำ (US ไม่ไปจับใน "bus") · ไทยไม่มีช่องว่างระหว่างคำ จับแบบซ้อนในได้
            $re = preg_match('/^[a-z .\'-]+$/', $t)
                ? '/(?<![a-z])' . preg_quote($t, '/') . '(?![a-z])/u'
                : '/' . preg_quote($t, '/') . '/u';
            if (preg_match($re, $hay)) {
                $found[$code] = true;
                $hay = preg_replace($re, ' ', $hay);
            }
        }
        return array_keys($found);
    }

    /** ลักษณะการเดินทาง (TMC, 2569-10-07) — เลือก หรือ "อื่น ๆ" แล้วพิมพ์ เก็บเป็นข้อความใน tmc_travel_style */
    public static $tmcTravelStyleOption = [
        'เดินทางเอง / Backpack', 'ไปกับคณะทัวร์', 'ไปกับครอบครัว/เพื่อน', 'ไปกับหน่วยงาน/บริษัท',
        'ไปกับคณะนักเรียน/สถานศึกษา', 'ไปกับคณะแสวงบุญ (ฮัจย์/อุมเราะห์)',
    ];

    /** migration 2026_10_07_120000 (เข้าป่า/ดำน้ำ/ลักษณะการเดินทาง) ขึ้นแล้วหรือยัง — ยังไม่ขึ้นห้ามเรนเดอร์ช่อง ไม่งั้นกด Save แล้ว SQL พัง */
    public static function tmcTravelExtraReady(): bool
    {
        static $ready = null;
        return $ready ??= \Illuminate\Support\Facades\Schema::hasColumn('visits', 'tmc_travel_style');
    }

    /** ประวัติการคุมกำเนิด (TMC) — เลือก "อื่น ๆ" แล้วพิมพ์ในช่องรายละเอียด */
    public static $tmcContraceptionOption = [
        'ไม่ได้คุม', 'ยาเม็ด', 'ยาฉีด', 'ยาฝัง', 'ห่วงอนามัย', 'ถุงยางอนามัย', 'ทำหมัน', 'อื่น ๆ',
    ];

    /** คอลัมน์ใหม่ของส่วนสุภาพสตรี TMC — ใช้เช็คว่า migration ขึ้นแล้วหรือยัง */
    public const TMC_FEMALE_COLS = ['tmc_lmp_end', 'tmc_contraception', 'tmc_contraception_other', 'tmc_last_sex_date'];

    /** แคชผลเช็คคอลัมน์ (เป็น property ไม่ใช่ static ในเมธอด — ชุดทดสอบตั้งค่าได้โดยไม่ต้องแตะโครงสร้างฐาน) */
    protected static $tmcFemaleReady = null;

    /** migration ส่วนสุภาพสตรี TMC ขึ้นแล้วหรือยัง (ถามฐานครั้งเดียวต่อคำขอ) */
    public static function tmcFemaleReady(): bool
    {
        if (self::$tmcFemaleReady === null) {
            self::$tmcFemaleReady = true;
            foreach (self::TMC_FEMALE_COLS as $c) {
                if (! \Illuminate\Support\Facades\Schema::hasColumn('visits', $c)) { self::$tmcFemaleReady = false; break; }
            }
        }
        return self::$tmcFemaleReady;
    }

    /**
     * ตรวจ/จัดรูปช่องใหม่ของส่วนสุภาพสตรี TMC ก่อนเขียนลง visits
     * - คอลัมน์ยังไม่ขึ้น ($ready=false) = ตัดคีย์ทิ้ง ไม่งั้น update() ล้มทั้งหน้า (Unknown column)
     * - วันที่: ตัวอ่าน พ.ศ. แบบเข้มงวด (ThaiDate) · ผิดรูปแบบ = ไม่เขียนช่องนั้น + เตือน (ช่องอื่นบันทึกตามปกติ)
     * - วันสุดท้ายของประจำเดือนต้องไม่ก่อน LMP (วันแรก) — อ่าน LMP จากฟอร์ม ถ้าไม่มีใช้ค่าเดิมใน visit
     * คืน [requestData, รายการคำเตือน]
     */
    public static function cleanTmcFemale(array $data, ?string $currentLmp = null, ?bool $ready = null, array $opts = []): array
    {
        $ready = $ready ?? self::tmcFemaleReady();
        $warn = [];
        if (! $ready) {
            foreach (self::TMC_FEMALE_COLS as $c) { unset($data[$c]); }
            return [$data, $warn];
        }

        $labels = ['tmc_lmp_end' => 'ประจำเดือนวันสุดท้าย', 'tmc_last_sex_date' => 'มีเพศสัมพันธ์ครั้งสุดท้าย'];
        foreach ($labels as $f => $label) {
            if (! array_key_exists($f, $data)) { continue; }
            $r = \App\Support\ThaiDate::parse($data[$f], ['allowEmpty' => true, 'notFuture' => true] + $opts);
            if (! $r['ok']) {
                $warn[] = $label . ': ' . $r['error'];
                unset($data[$f]);
                continue;
            }
            $data[$f] = $r['be'] ? \App\Support\ThaiDate::show($r['be']) : null;
        }

        if (! empty($data['tmc_lmp_end'])) {
            $startRaw = array_key_exists('LMP', $data) ? $data['LMP'] : $currentLmp;
            $days = self::lmpDays($startRaw, $data['tmc_lmp_end']);
            if ($days !== null && $days < 1) {
                $warn[] = 'ประจำเดือนวันสุดท้าย (' . $data['tmc_lmp_end'] . ') อยู่ก่อนวันแรก (' . $startRaw . ') — ไม่ได้บันทึกช่องนี้';
                unset($data['tmc_lmp_end']);
            }
        }

        if (array_key_exists('tmc_contraception', $data)) {
            $c = trim((string) $data['tmc_contraception']);
            // ค่าที่ไม่อยู่ในตัวเลือก (แก้ HTML/ข้อมูลแปลก) ไม่ทิ้ง — ย้ายไปช่องรายละเอียดแทน
            if ($c !== '' && ! in_array($c, self::$tmcContraceptionOption, true)) {
                $data['tmc_contraception_other'] = trim($c . ' ' . ($data['tmc_contraception_other'] ?? ''));
                $c = 'อื่น ๆ';
            }
            $data['tmc_contraception'] = $c === '' ? null : $c;
        }
        if (array_key_exists('tmc_contraception_other', $data)) {
            $o = mb_substr(trim((string) $data['tmc_contraception_other']), 0, 255);
            $data['tmc_contraception_other'] = $o === '' ? null : $o;
        }

        return [$data, $warn];
    }

    /**
     * จำนวนวันของประจำเดือน นับรวมวันแรกและวันสุดท้าย (16/09 – 20/09 = 5 วัน)
     * อ่านไม่ได้ฝั่งใดฝั่งหนึ่ง = null · วันสุดท้ายก่อนวันแรก = ค่า <= 0
     */
    public static function lmpDays($start, $end): ?int
    {
        $a = \App\Support\ThaiDate::parse($start);
        $b = \App\Support\ThaiDate::parse($end);
        if (! $a['ok'] || ! $b['ok'] || ! $a['be'] || ! $b['be']) { return null; }
        $toAd = fn ($be) => \Carbon\Carbon::create((int) substr($be, 0, 4) - 543, (int) substr($be, 5, 2), (int) substr($be, 8, 2));
        return (int) $toAd($a['be'])->diffInDays($toAd($b['be']), false) + 1;
    }


    
// app/Models/Visit.php
public function contactPersons()
{
    // เชื่อมโยงผ่าน visit_id
    return $this->hasMany(\App\Models\ContactPerson::class, 'visit_id', 'id');
}
public function registrationUser(){ return $this->belongsTo(User::class, 'registration_by'); }
public function nurseUser()       { return $this->belongsTo(User::class, 'nurse_by'); }
public function doctorUser()      { return $this->belongsTo(User::class, 'doctor_by'); }
public function labUser()         { return $this->belongsTo(User::class, 'lab_by'); }          // 👈 เพิ่มอันนี้
public function labSenderUser()   { return $this->belongsTo(User::class, 'lab_by'); }          // (ทางเลือก กันพัง)
public function pharmacyUser()    { return $this->belongsTo(User::class, 'pharmacy_by'); }
public function handledByUser()   { return $this->belongsTo(User::class, 'handled_by'); }



    protected $casts = [
        'email_verified_at' => 'datetime',
        'body_check_marks' => 'array',
        'trush_tracks' => 'array',
        // แผนนัดฉีดวัคซีน TMC: [{date:"15/08/2569", vaccines:[...]}, ...]
        'tmc_vaccine_plan' => 'array',
        // การตรวจร่างกาย TMC แบบสวิตช์ระบบ (2569-09-30) — ข้อความรวมเขียนลง body_check ด้วย
        'tmc_pe' => 'array',
        
        // 'date' => 'date',  <-- 🔴 ลอง Comment บรรทัดนี้ออกก่อนครับ ตัวนี้มักจะมีปัญหาถ้าข้อมูลเป็น 0000-00-00
        
        // ส่วนเวลาที่เพิ่มใหม่ ให้คงไว้ (ถ้าข้อมูลใน DB เป็น NULL มันจะไม่ Error)
        'room_entered_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'appointment_confirm_sent_at' => 'datetime',
        'registration_at' => 'datetime',
        'screening_at' => 'datetime',
        'doctor_at' => 'datetime',
        'pharmacy_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
    public static $knowFromOption = [0 => '',1 => 'จนท.ในสถานศึกษา,สถานบันเทิง,ที่ทำงานแนะนำ', 2 => 'คนที่ที่ไม่ใช่เจ้าหน้าที่แนะนำ', 3 => 'สื่อต่าง ๆ', 4 => 'เคยมา', 5 => 'ส่งต่อมาจาก', 10 => 'อื่น ๆ'];

    public static $referOption = ['M Plus', 'Caremat', 'PHPT', 'MAP', 'กาชาด', 'อื่น ๆ'];

    public static $QueueOption = ['เรียกคิวแล้ว', 'SimpPrEP', 'Caremat', 'Line ', 'ส่งมาจากห้อง...', 'เปิดสิทธิ Anti-HCV + HBsAg', 'เปิดสิทธิ Anti-HCV อย่างเดียว', 'ไม่ได้รับสิทธิตรวจ Hepatitis เกิดหลัง 2535', 'ตรวจ Hepatitis แล้ว','N/A','ทำบัตรประจำตัว/สมุดใหม่','M-Plus','กาชาด'];
    

    public static $otherOption = [0 => '', 1 => 'ส่งต่อเพื่อรักษาโรคซิฟิลิส', 2 => 'ส่งต่อเพื่อรักษาหนองใน', 3 => 'ส่งต่อเพื่อรักษาหูด'];


    public static $reasonStiOption = [ 1 => 'ต้องการตรวจสุขภาพ', 2 => 'มีอาการผิดปรกติ', 3 => 'คู่สัมผัสมีอาการ', 4 => 'ติดตามผลการรักษา', 5 => 'ฟังผล LAB', 
    6 => 'ข้อบังคับสถานบริการ', 
    7 => 'ผลตรวจห้องปฏิบัติการผิดปกติ',
    10 => 'อื่น ๆ'];

    public static $reasonVctOption = [
         1 => 'ต้องการตรวจสุขภาพ', 2 => 'มีพฤติกรรมเสี่ยง เช่น ไม่ใช้ถุงยาง/แตก/รั่ว', 3 => 'เคยได้รับการถ่ายเลือด/ปลูกถ่ายอวัยวะ',
        4 => 'มีประวัติใช้เข็มฉีดยาร่วมกัน', 5 => 'มีประวัติสักยันต์,เจาะร่างกายโดยใช้ของร่วมกัน', 6 => 'กังวลว่าจะมีการติดเชื้อจากแม่สู่ลูก', 10 => 'อื่น ๆ'
    ];


    public static $riskBehaviorOption = [
        1 => 'มีเพศสัมพันธ์กับหญิงบริการโดยไม่ใช้ถุงยาง', 2 => 'คู่นอนมากกว่า 1 คน', 3 => 'เป็นคู่นอนใหม่', 4 => 'คู่นอนเป็นโรค STI', 5 => 'ไม่ใช้ถุงยาง/แตก/รั่ว',
        6 => 'ใช้ยาเสพติด/ดื่มสุรา ก่อนมีเพศสัมพันธ์', 7 => 'มีเพศสัมพันธ์แบบหมู่', 8 => 'มีเพศสัมพันธ์ขณะเป็นโรค STI',8 => 'อื่น ๆ'
    ];

    public static $symptomOption = [
        'Case ซิฟิลิส รักษาครบเมื่อ...วันนี้มาติดตามผล RPR หลังการรักษา รอบ...เดือน/มาฟังผลเลือด RPR รอบ...เดือน (ผล= : ) ไม่มีผื่น, ไม่มีแผล, ไม่มีผมร่วง, ไม่มีอาการผิดปกติ/มาฉีดยาตามนัด เข็มที่...ไม่มีอาการผิดปกติหลังฉีดยาครั้งก่อน',
        'Case....รักษาเมื่อ....วันนี้มาติดตามผลการรักษา/ขอฟังผลเลือดหลังการรักษา/ไม่มีอาการผิดปกติ',
        'Case....วันนี้มาติดตามอาการหลังการรักษา ไม่มีหนอง/น้ำ ปัสสาวะปกติ',
        'Case wart วันนี้มาป้ายยาและดูอาการ หลังการรักษาครั้งก่อน วันนี้อาการดีขึ้น ติ่งเนื้อลดลง ไม่มีแผลจากการป้ายยา',
        'Case....วันนี้มาติดตามอาการหลังการรักษา ไม่มีตกขาว ไม่คัน ไม่มีกลิ่น ปัสสาวะปกติ',
        '....ก่อนมา มีหนองไหล สีใส/เหลืองขุ่น มีปัสสาวะแสบขัด ไม่มีก้อน ยังไม่เคยซื้อยา/รักษามาก่อน', 
        '....ก่อนมา มีติ่งเนื้อ/ตุ่มน้ำใส มีแผลบริเวณ.... ตุ่มแผลไม่ปวด',
        '....ก่อนมา มีตกขาว มีอาการคันบริเวณ....มีกลิ่น ปัสสาวะปกติ',
        'ต้องการตรวจสุขภาพ อาการปกติ',
        'ตรวจคัดกรองการติดเชื้อไวรัสตับอักเสบบีและซีตามสิทธิ์',
        'มาฉีดวัคซีน..dose ที่.. ก่อนเดินทาง/ตามนัด/หลังนัด หลังฉีดไม่มีอาการผิดปกติหรือแพ้ยา  วันนี้อาการปกติดี',
        'อส.โครงการวิจัย SimpPrEP มารับยาต่อ M.12 วันนี้มารับยาตามนัด ช่วงที่ผ่านมาสบายดี รับประทานยาโครงการวิจัยได้ดี ไม่มีอาการผิดปกติ ไม่ได้นอนรพ., No new AE/Con Med.',
        'case ......syphilis หลังการรักษา........เดือน ขอฟังผล RPR ทางไลน์'
    ];

    public static $ReasonStiOtherOption = [
        'อส.โครงการวิจัย SimpPrEP มารับยาต่อ M.',
        'ส่งต่อจาก.......เพื่อรักษาซิฟิลิส'
        // เพิ่มตัวเลือกอื่น ๆ ได้ภายหลัง
    ];

    public static $consultationOption = [1 => 'ได้ทำ', 2 => 'ไม่ได้ทำ'];

    /* ---------- การตรวจร่างกาย TMC: normal all + สวิตช์ระบบที่ผิดปกติ (คลินิกขอ 2569-09-30) ---------- */

    public static $tmcPeSystems = [
        'heent' => 'HEENT', 'skin' => 'Skin', 'chest' => 'Lung/Chest', 'heart' => 'Heart',
        'abdomen' => 'Abdomen', 'ext' => 'Ext.', 'neuro' => 'Neurology',
    ];

    /** รับค่าจากฟอร์ม เก็บเฉพาะระบบที่เปิดสวิตช์ผิดปกติ (พร้อมข้อความ) */
    public static function cleanTmcPe($in): array
    {
        $in  = is_array($in) ? $in : [];
        $abn = (array) ($in['abn'] ?? []);
        $txt = (array) ($in['text'] ?? []);
        $sys = [];
        foreach (array_keys(self::$tmcPeSystems) as $k) {
            if (! empty($abn[$k]) && $abn[$k] !== '0') {
                $sys[$k] = mb_substr(trim((string) ($txt[$k] ?? '')), 0, 500);
            }
        }

        return [
            'normal_all' => ! empty($in['normal_all']) && $in['normal_all'] !== '0' ? 1 : 0,
            'sys'        => $sys,
            'note'       => mb_substr(trim((string) ($in['note'] ?? '')), 0, 2000),
        ];
    }

    /**
     * ข้อความรวมลง body_check — ใบพิมพ์/ประวัติ/ปุ่มดูค่าเดิมยังอ่านช่องนี้อยู่
     * เช่น "PE: normal all" หรือ "PE: HEENT: injected pharynx; Skin: rash · ระบบอื่นปกติ"
     */
    public static function tmcPeText(array $pe): string
    {
        $parts = [];
        foreach ((array) ($pe['sys'] ?? []) as $k => $t) {
            $parts[] = (self::$tmcPeSystems[$k] ?? $k) . ': ' . ($t !== '' ? $t : 'ผิดปกติ');
        }
        $line = '';
        if ($parts) {
            $line = 'PE: ' . implode('; ', $parts) . (! empty($pe['normal_all']) ? ' · ระบบอื่นปกติ' : '');
        } elseif (! empty($pe['normal_all'])) {
            $line = 'PE: normal all';
        }
        $note = trim((string) ($pe['note'] ?? ''));

        return trim($line . ($note !== '' ? ($line !== '' ? "\n" : '') . $note : ''));
    }

    public static $termSyphilisOption = [1 => '1', 2 => '2', 3 => 'Early Latent', 4 => 'Late Latent', 5 => 'Unknown Duration'];
    public static $diseaseStateOption = [1 => 'ผู้ป่วยใหม่', 2 => 'เกิดโรคซ้ำ', 3 => 'อยู่ระหว่างรักษา', 4 => 'มาติดตามผลการรักษา', 5 => 'ส่งต่อ', 5 => 'ไม่ได้รับการรักษา'];
    /**
     * ผลแล็บครั้งล่าสุดที่ตรวจที่คลินิกนี้ "ก่อนวันที่ของ visit นี้" แยกตามชนิด (method ของ lab_subs) — STI ขอ 2569-10-09
     * ใช้เป็นคำแนะนำในช่องประวัติการตรวจ HIV / Syphilis / Hep B-C ของหน้าตรวจ (กดใช้เอง ไม่เขียนทับ)
     * นับเฉพาะผลที่โชว์ได้ (Lab::isResultVisible = อนุมัติแล้ว หรือ lab ยุคก่อนกติกา)
     * คืน [method => ['result' => ..., 'date' => 'd/m/yy' (พ.ศ.)], ...] เฉพาะชนิดที่เคยตรวจ
     */
    public function previousLabResults(array $methods): array
    {
        $vDate = (string) $this->getRawOriginal('date');
        if ($vDate === '' || ! $this->patient_id) { return []; }

        $rows = \App\Models\LabSub::query()
            ->join('labs', 'labs.id', '=', 'lab_subs.lab_id')
            ->join('visits', 'visits.id', '=', 'labs.visit_id')
            ->where('visits.patient_id', $this->patient_id)
            ->where('visits.id', '!=', $this->id)
            ->where('visits.date', '<', $vDate)
            ->whereIn('lab_subs.method', $methods)
            ->whereNotNull('lab_subs.result')->where('lab_subs.result', '!=', '')
            ->orderByDesc('visits.date')->orderByDesc('lab_subs.id')
            ->limit(40)->get(['lab_subs.method', 'lab_subs.result', 'lab_subs.lab_id', 'visits.date as vdate']);

        $out = [];
        $visible = [];
        foreach ($rows as $r) {
            $m = (int) $r->method;
            if (isset($out[$m])) { continue; }
            if (! array_key_exists($r->lab_id, $visible)) {
                $lab = \App\Models\Lab::find($r->lab_id);
                $visible[$r->lab_id] = $lab && $lab->isResultVisible();
            }
            if (! $visible[$r->lab_id]) { continue; }
            if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) $r->vdate, $x)) { continue; }
            $y = (int) $x[1]; if ($y < 2500) { $y += 543; }   // ข้อมูลยุคแรกบางแถวเป็น ค.ศ.
            $out[$m] = ['result' => trim((string) $r->result), 'date' => ((int) $x[3]) . '/' . ((int) $x[2]) . '/' . substr((string) $y, -2)];
        }
        return $out;
    }

    public static $hivtestresultOption = [0 => '',1 => 'Negative',2 => 'Inconclusive',3 => 'Positive' ];

    public static $diseaseStateOption1 = [1 => 'ผู้ป่วยใหม่(new case)', 2 => 'เกิดโรคซ้ำ(recurrent,reinfection)', 3 => 'การรักษาเดิมไม่ได้ผล(treatment  failure)', 4 => 'มาติดตามดูอาการ/รับการรักษาต่อเนื่อง(follow up)', 
    5 => 'มาฉีดยาpenicillin เข็มที่1', 5 => 'มาฉีดยาpenicillin เข็มที่2', 6 => 'มาฉีดยาpenicillin เข็มที่3', 7 => 'มาฟังผลทางห้องปฏิบัติการ(Lab test)', 8 => 'มาฟังผลเลือด RPR', 
    9 => 'มาฟังผลเลือด RPR หลังรักษา 3เดือน', 10 => 'มาฟังผลเลือด RPR หลังรักษา 6เดือน', 11 => 'มาฟังผลเลือด RPR หลังรักษา 9เดือน', 12 => '2มาฟังผลเลือด RPR หลังรักษา 12เดือน', 
    13 => 'มาฟังผลเลือด RPR หลังรักษา 24เดือน', 14 => 'สิ้นสุดการรักษา(completed)'];

    public static $hivStiTestResuleOption = [1 => 'รายเดี่ยว', 2 => 'รายกลุ่ม', 3 => 'พร้อมคู่', 4 => 'ไม่เคยตรวจ', 5 => 'เคยตรวจ'];

    public static $touchTracingOption = [0=>'', 1 => 'ฝากยา', 2 => 'มาตรวจพร้อมกันครั้งนี้', 3 => 'ออกใบติดตาม', 4 => 'ผู้ป่วยจะแจ้งเอง', 5 => 'จนท.ตามให้', 5 => 'ไม่สามารถติดตามได้', 6 => 'คู่รักษาแล้ว' ];

    public static $ClinicOption = [1 => 'STI Clinic', 2 => 'TB Clinic', 3 => 'TMC clinic'];

    // ===== คลินิกที่กำลังทำงานอยู่ (ปุ่มสลับบน navbar) =====
    // ระบบเดียวใช้ร่วมกัน 3 คลินิก (STI/TB/TMC) โดยแยกด้วยคอลัมน์ visits.clinic
    // คนไข้ (patients) เป็นฐานกลางร่วมกัน ไม่แยกตามคลินิก เพราะคนเดียวกันมาได้หลายคลินิก
    public static $ClinicShortName = [1 => 'STI', 2 => 'TB', 3 => 'TMC'];
    public static $ClinicColor     = [1 => '#0d6efd', 2 => '#b45309', 3 => '#7c3aed'];

    // สีแถบบนสุด แยกตามคลินิก เพื่อให้เห็นแต่ไกลว่ากำลังทำงานคลินิกไหนอยู่
    // STI คงโทนเดิมไว้ เพราะเป็นงานส่วนใหญ่และเจ้าหน้าที่ชินกับสีนี้แล้ว
    public static $ClinicNavGradient = [
        1 => ['#0ea5e9', '#6366f1'],   // STI — ฟ้า→คราม (เดิม)
        2 => ['#f59e0b', '#b45309'],   // TB  — ส้ม→น้ำตาล
        3 => ['#a855f7', '#6d28d9'],   // TMC — ม่วงอ่อน→ม่วงเข้ม
    ];

    /** CSS gradient ของแถบบนสุดสำหรับคลินิกที่ระบุ (ไม่ระบุ = คลินิกที่เลือกอยู่) */
    public static function navGradient(?int $clinic = null): string
    {
        $c = $clinic ?? self::activeClinic();
        [$a, $b] = self::$ClinicNavGradient[$c] ?? self::$ClinicNavGradient[1];
        return "linear-gradient(90deg, {$a} 0%, {$b} 100%)";
    }

    /** คลินิกที่เลือกอยู่ตอนนี้ (ค่าเริ่มต้น = STI) */
    public static function activeClinic(): int
    {
        $c = (int) session('active_clinic', 1);
        return array_key_exists($c, self::$ClinicOption) ? $c : 1;
    }

    /**
     * กรองเฉพาะคลินิกที่เลือกอยู่ — ใช้กับ "รายการงาน" เท่านั้น
     * (ไม่ใช้เป็น global scope โดยตั้งใจ เพราะการเปิด visit ตรงๆ ด้วย id
     *  หรือดูประวัติคนไข้ข้ามคลินิก ต้องยังทำได้อยู่)
     */
    public function scopeCurrentClinic($query, ?int $clinic = null)
    {
        return $query->where('clinic', $clinic ?? self::activeClinic());
    }
    // status = รหัสภายใน ส่วนเลขท้ายชื่อ = "เลขห้องจริง" ที่ติดหน้าห้อง (คนละชุดกัน อย่าสับสน)
    //   2 -> ห้อง 4 (ตรวจชาย) · 8 -> ห้อง 5 (ตรวจหญิง) · 3 -> ห้อง 6 (เจาะเลือด/ฉีดยา)
    //   7 -> ห้อง 7 (TMC)     · 5 -> ห้องยา (ห้อง 10)   · 9 -> ห้องการเงิน (ห้อง 11)   · 6 -> กลับบ้าน
    //   10 -> ห้องตรวจวัณโรค (ห้อง 2) — คลินิก TB เดินห้อง 2 -> 6 (เจาะเลือด) -> 10 (ห้องยา/เก็บเงิน)
    // เส้นทางที่ใช้จริงตั้งแต่ 2569-09-11:
    //   พยาบาล/หมอ -> ห้องยา (5) จ่ายยา+ตัดสต๊อก+คิดเงิน+รับเงิน -> กลับบ้าน (6)
    //   กองคลังแจ้งว่าออกใบเสร็จจากคอมไม่ได้ ต้องเขียนจากเล่ม คนไข้จึงไม่ต้องเดินไปห้องการเงิน
    //   status 9 (ห้องการเงิน) ไม่อยู่ในเส้นทางเดินแล้ว แต่คงไว้เพราะหน้าการเงินยังใช้ดู/แก้บิลย้อนหลัง
    // (ก่อนหน้านี้ 5 = "ห้องยา/การเงิน" รวมกัน — ห้ามลบชื่อ 5 หรือเปลี่ยนความหมาย เพราะข้อมูลเก่ายังอ้างอิงอยู่)
    public static $statusOption = [
        1 => 'ลงทะเบียน',
        2 => 'ห้องตรวจชาย 4',
        8 => 'ห้องตรวจหญิง 5',
        3 => 'ห้องเจาะเลือด/ฉีดยา 6',
        7 => 'ห้องตรวจ 7 (TMC)',
        10 => 'ห้องตรวจวัณโรค 2',
        9 => 'ห้องการเงิน',
        5 => 'ห้องยา',
        6 => 'กลับบ้าน',
    ];

    /**
     * status ของ visit -> เลขห้องจริงบนจอเรียกคิว
     *
     * ต้องมีตารางนี้เพราะสองระบบใช้คนละเลข (status 2 = ห้องตรวจชาย ซึ่งติดป้ายหน้าห้องว่า "4")
     * ใช้ย้ายคิวตามคนไข้เวลาส่งข้ามห้อง — ไม่งั้นคิวค้างอยู่ห้องเดิมทั้งที่คนไข้เดินไปแล้ว
     * (พยาบาลแจ้ง 2569-09-21: ห้อง 4 ส่งคนไข้ไปห้อง 6 แต่คิวยังอยู่ห้อง 4)
     *
     * ลงทะเบียน (1) กับ กลับบ้าน (6) ไม่มีห้องบนจอคิว จึงไม่อยู่ในตาราง
     */
    public static $statusQueueRoom = [
        2  => 4,    // ห้องตรวจชาย
        8  => 5,    // ห้องตรวจหญิง
        3  => 6,    // เจาะเลือด/ฉีดยา
        7  => 7,    // TMC
        10 => 2,    // ห้องตรวจวัณโรค
        5  => 10,   // ห้องยา/เก็บเงิน
        9  => 11,   // ห้องการเงิน
    ];

    /** ห้องในระบบคิวกลางที่แต่ละคลินิกใช้ — กันไม่ให้เลือกคิวข้ามคลินิกกัน */
    public static $ClinicQueueRooms = [
        1 => [4, 5, 6],   // STI
        2 => [2, 6, 10],  // TB — ห้องตรวจวัณโรค 2 · เจาะเลือด 6 · ห้องยา/เก็บเงิน 10 (พี่กานต์ยืนยัน 2569-09-18)
        3 => [7],         // TMC
    ];

    public static function queueRooms(?int $clinic = null): array
    {
        $c = $clinic ?? self::activeClinic();
        return self::$ClinicQueueRooms[$c] ?? [4, 5, 6];
    }


    public static $treatmentOption = [1 => 'แนะนำให้ใช้ถุงยางอนามัยทุกครั้งในทุกช่องทางเมื่อมีเพศสัมพันธ์และสอนการใส่ถุงยางอย่างถูกต้อง',
    2 => 'ให้คำปรึกษาก่อน (pre-test) และหลังการตรวจเลือดหาการติดเชื้อเอชไอวีและซิฟิลิส (post-test)',
    3 => 'แนะนำตรวจเลือดหาเชื้อเอชไอวีและซิฟิลิสหลังพ้นระยะฟักตัว แนะนำการรับยา PrEP',
    4 => 'สังเกตอาการแพ้ยาหลังฉีดยา 30 นาที และแนะนำการสังเกตอาการแพ้ยาและอาการข้างเคียงเมื่อกลับบ้าน',
    5 => 'ให้ข้อมูลการถ่ายทอดเชื้อไปสู่คู่เพศสัมพันธ์ และการติดตามผู้สัมผัสเพื่อเข้ารับการรักษาร่วมด้วย',
    6 => 'ให้ข้อมูลเกี่ยวกับโรคที่กำลังป่วยอยู่และการปฏิบัติตัวเพื่อไม่ให้รับเชื้อเพิ่มหรือป้องกันไม่ให้ติดเชื้อซ้ำ ความจำเป็นของการรักษาอย่างครบถ้วนและมารับการติดตามอาการอย่างต่อเนื่อง',
    7 => 'ให้คำแนะนำ เรื่อง งดมีเพศสัมพันธ์และสำเร็จความใคร่ด้วยตนเองระหว่างการรักษา หากงดไม่ได้ควรใช้ถุงยางอนามัยทุกครั้ง มีคู่เพศสัมพันธ์คนเดียว งดเว้นการเปลี่ยนคู่เพศสัมพันธ์ งดการสวนล้างช่องคลอด ควรเปลี่ยนผ้าอนามัยบ่อยไม่ให้อับชื้น สังเกตอาการผิดปกติ หากมีอาการมากขึ้นหรือไม่ดีขึ้นให้มาตรวจใหม่หรือไปโรงพยาบาล ฯลฯ'];


    public static $reasonAppointmentOption = ['ติดตามผลการรักษา','รักษาต่อเนื่อง','รับยาตามนัด','ฟังผลเลือด','ฉีดยา penicillinเข็ม1','ฉีดยา penicillin เข็ม2',
    'ฉีดยา penicillin เข็ม3','เจาะ RPR หลังรักษา','เจาะ RPR หลังรักษา 3เดือน','เจาะ RPR หลังรักษา 6เดือน','เจาะ RPR หลังรักษา 9เดือน','เจาะ RPR หลังรักษา 12เดือน',
    'เจาะ RPR หลังรักษา 24เดือน','(แจ้งเตือนทางไลน์แล้ว)','ป้ายยา', 'vaccine mpox dose 2', 'ฟังผลเลือด RPR ทางไลน์'];

    public static $ContraceptionOption = ['DMPA','OCP','IUD','ไม่ได้คุม','Condom'];



    public  function contactPerson()
    {
        return ContactPerson::where('visit_id', $this->id)->get();
    }

    public  function lab()
    {
        return Lab::where('visit_id', $this->id)->first();
    }
    public  function labRef()
    {
        return $this->hasOne(\App\Models\Lab::class, 'visit_id');
    }
    public  function labItem()
    {
        $lab = $this->lab();
        if ($lab) {
            return LabItem::where('lab_id', $lab->id)->get();
        }
        return [];
    }

    public function labSub()
    {
        $lab = $this->lab();
        if ($lab) {
            return LabSub::where('lab_id', $lab->id)->get();
        }
        return [];
    }
    public function latestLabMethod()
{
    return $this->hasOneThrough(LabSub::class, Lab::class, 'visit_id', 'lab_id', 'id', 'id')
        ->whereIn('lab_subs.method', [11, 12])
        ->latest('lab_subs.created_at');
}

    public  function patient()
    {
        $patient = Patient::find($this->patient_id);
        return $patient;
        
    }
    // App\Models\Visit.php
    // สร้างฟังก์ชัน Relationship ใหม่
    public function patient2()
    {
        return $this->belongsTo(Patient::class, 'patient_id'); // ใช้ตารางเดิมได้ แต่ตั้งชื่อฟังก์ชันใหม่
    }
    public static function format2DB($requestData)
    {
        if (!array_key_exists('date', $requestData)) {
            $requestData['date'] =  Utillity::AD2BE(date('Y-m-d'));
        } else {
            $requestData['date'] = Utillity::th2dbDate($requestData['date']);
        }

        if (array_key_exists('appointment', $requestData)) {

            $requestData['appointment'] = Utillity::th2dbDate($requestData['appointment']);
        }

        if (array_key_exists('onset_date', $requestData)) {
            $requestData['onset_date'] = Utillity::th2dbDate($requestData['onset_date']) ?: null;
        }

        if (!array_key_exists('reason_sti', $requestData)) {
            $requestData['reason_sti'] =null;
        }
        // $requestData['reason_sti'] = implode(",",$requestData['reason_sti']);
        
        return $requestData;
    }

    public  function formatDBBack()
    {
        $this->date = Utillity::db2thDate($this->date);
        $this->appointment = Utillity::db2thDate($this->appointment);
        $this->onset_date = Utillity::db2thDate($this->onset_date);
        $this->reason_sti = json_decode($this->reason_sti,true);
        $this->reason_vct = json_decode($this->reason_vct,true);
        $this->risk_behavior = json_decode($this->risk_behavior,true);
        
    }
    public static function getPatient($visit = null)
    {
        if ($visit) {
            return $visit->patient();
        } elseif ($_GET['patient']) {
            $patient = Patient::find($_GET['patient']);
            return $patient;
        }
        return null;
    }
    
    public function visitMedicine()
    {
        return VisitMedicine::where('visit_id', $this->id)->get();
    }
    public function visitMedicines()
{
    // เชื่อมโยงไปหาตาราง visit_medicines
    return $this->hasMany(\App\Models\VisitMedicine::class, 'visit_id');
}
    public function diagnosi()
    {
        return Diagnosi::where('visit_id', $this->id)->get();
    }

    /**
     * ลิงก์ปุ่ม Action ในรายการห้อง
     * เดิมถ้าเจอ status ที่ไม่รู้จักจะคืนค่าว่าง ทำให้ href="" กดแล้วไม่ไปไหน
     * (เพิ่งเกิดกับห้อง 7 ตอนสร้างใหม่) จึงใส่ default ไว้กันเงียบแบบนั้นอีก
     */
    public function roomLink()
    {
        switch ((int) $this->status) {
            case 2:
                return route("visit.edit", ["visit" => $this->id, "page" => 2]);
            case 3:
                return route("visit.edit", ["visit" => $this->id, "page" => 3]);
            case 8:
                return route("visit.edit", ["visit" => $this->id, "page" => 8]);
            case 5:
                return route("visit.edit", ["visit" => $this->id, "page" => 5]);
            case 7:   // ห้องตรวจ 7 (TMC) — ใช้หน้าเดียวกับห้องตรวจ
                return route("visit.edit", ["visit" => $this->id, "page" => 2]);
        }
        return route("visit.edit", ["visit" => $this->id, "page" => 2]);
    }

    /**
     * จำนวนคนไข้ที่ค้างอยู่ในห้อง (ป้ายตัวเลขบนเมนู)
     * ไม่กรองคลินิก เพื่อให้ตรงกับรายการห้องที่แสดงทุกคลินิก (VisitController@room)
     * — ตัวเลขบนป้ายกับจำนวนแถวที่กดเข้าไปเห็นต้องตรงกันเสมอ
     */
    public static function count($status)
    {
        return Visit::where('status', $status)->count();
    }


    public static function next($page, $id)
    {
        switch ($page) {
            case 1:
                return redirect(route('patient.edit', ['patient' => $id, 'page' => 1]));
                break;
            case 2:
                return redirect(route('visit.edit', ['visit' => $id, 'page' => 2]));
                break;

            case 3:
                return redirect(route('visit.edit', ['visit' => $id, 'page' => 3]));
                break;

            case 4:
                return redirect(route('lab.edit', ['lab' => $id, 'page' => 4]));
                break;

            case 5:
                return redirect(route('visit.lab', ['visit' => $id, 'page' => 5]));
                break;

        }
    }



    public function diagnosiAllString()
    {
        $result = [];
        // TB: หมอลงวินิจฉัยที่ ICD-10 ของใบคัดกรองช่องเดียว (2569-10-09) — เอามาขึ้นในประวัติ/ใบพิมพ์ก่อนรายการแบบเดิม
        if ((int) $this->clinic === 2) {
            $scId = \App\Models\TbScreening::where('visit_id', $this->id)->value('id');
            foreach (\App\Models\Icd10Code::typedEntriesFor(\App\Models\TbScreening::icd10Owner(), $scId) as $e) {
                if ($e['code']) {
                    $info = \App\Models\Icd10Code::info($e['code']);
                    $result[] = $e['code'] . ($info ? ' ' . ($info->description_th ?: $info->description_en) : '');
                } elseif (trim((string) $e['text']) !== '') {
                    $result[] = trim((string) $e['text']);
                }
            }
        }
        $diagnosis =  Diagnosi::where('visit_id', $this->id)->get();
        foreach($diagnosis as $diagnosi){
            $result[] = $diagnosi->disease();
        }
        return implode(",", $result);
    }
    public function diagnosiStatusAllString()
    {
        $result = [];
        $diagnosis =  Diagnosi::where('visit_id', $this->id)->get();
        foreach($diagnosis as $diagnosi){
            $result[] = $diagnosi->diseaseState();
        }
        return implode(",", $result);
    }
    public function reasonString()
    {
        $result = [];
        if ($this->reason_sti) {
            $reason_stis = json_decode($this->reason_sti,true);
            foreach($reason_stis as $reason_sti){
                $result[] = self::$reasonStiOption[$reason_sti];
            }
            
        }
        if ($this->reason_vct) {

            $reason_vcts = json_decode($this->reason_vct,true);
            foreach($reason_vcts as $reason_vct){
                $result[] = self::$reasonVctOption[$reason_vct];
            }
            // $result[] = self::$reasonVctOption[$this->reason_vct];
        }
        return implode(",", $result);
    }

    public function diagnosiString()
    {
        $diagnosis =  Diagnosi::where('visit_id', $this->id)->get();
        $result = [];
        foreach($diagnosis as $diagnosi){
            if($diagnosi->disease){
                $result[] = Diagnosi::$diseaseOption[$diagnosi->disease];
            }else{
                $result[] = $diagnosi->other_disease;
            }
        }
        return  implode(", ",$result);
    }

    public function diseaseStateString()
    {
        $diagnosis =  Diagnosi::where('visit_id', $this->id)->get();
        $result = [];
        foreach($diagnosis as $diagnosi){
            if($diagnosi->disease){
                $result[] = Diagnosi::$diseaseStateOption[$diagnosi->disease_state] ?? '';   // TMC/TB ไม่มีสถานะ (ว่าง)
            }else{
                $result[] = $diagnosi->disease_state_other;
            }
        }
        return  implode(", ",$result);
    }
    public function RPR()
    {
        $lab = Lab::where('visit_id', $this->id)->first();
        if (!$lab) { return ''; }   // visit นี้ยังไม่มี lab เลย (เช่น visit ใหม่/นัดหมายเฉยๆ) — กัน 500 ที่ Visit History
        $labSubs = LabSub::where('lab_id', $lab->id)->where('method', 8 /*RPR*/)->get();

        $result = [];
        foreach($labSubs as $labSub){
            if($labSub->result){
                $result[] = $labSub->result;
            }
        }
        return  implode(", ",$result);
    }
    public function trush_tracks()
    {
            if( $this->trush_tracks){
            $result = json_decode($this->trush_tracks);
            return $result;
        }
        return [];
    }
    public function diagnoses()
{
    return $this->hasMany(Diagnosi::class, 'visit_id');
}
    public function followups()
{
    return $this->hasMany(VisitFollowup::class);
}
    public function certificates()
{
    return $this->hasMany(VisitCertificate::class)->latest();
}

    /**
     * ซิงก์คิวกลาง (โปรแกรมคิวบนจอ terminal) ให้ตรงกับสถานะของ visit
     *
     *  - กลับบ้าน (status 6) -> ปิดคิว ไม่ให้ค้างกระพริบบนจอ
     *  - ห้องอื่น            -> ถ้าคิวถูกปิดไว้ ให้เปิดกลับมา
     *
     * ข้อหลังจำเป็นเพราะเคยเกิดจริง (2026-08-05, คิว 1): เผลอกดกลับบ้าน คิวถูกปิด
     * แล้วย้ายคนไข้กลับเข้าห้องเจาะเลือด — สถานะใน visit ถูก แต่คิวค้าง completed
     * คนไข้เลยหายจากจอทั้งที่ยังนั่งรออยู่ และไม่มีทางเปิดกลับเองได้เลย
     * ("ห้อง 6" กับ "status 6 = กลับบ้าน" เป็นเลขเดียวกัน เลยกดพลาดกันง่ายมาก)
     *
     * ไม่แตะคิวที่กำลังถูกเรียก (called) เพื่อไม่ให้เสียสถานะเรียกที่ห้องกดไว้
     */
    public function syncCentralQueue(): void
    {
        try {
            if (!$this->queue_system_id) { return; }

            $q = \App\Models\QueueSystemQueue::find($this->queue_system_id);
            if (!$q) { return; }

            if (\App\Enums\VisitStatus::Home->is($this->status)) {
                if ($q->status !== 'completed') {
                    $q->update(['status' => 'completed', 'is_calling' => 0]);
                }
                return;
            }

            // เปิดคิวกลับ/ย้ายห้อง ทำเฉพาะ visit ของวันนี้ที่ผูกกับคิวที่ออกวันนี้
            // เดิมพยาบาลเปิด visit เมื่อวานมาแก้แล้วกดบันทึก → คิวเมื่อวานกลับขึ้นจอ + created_at กลายเป็นวันนี้
            // ห้องบัตรเลยออกเลขต่อจากเมื่อวาน (add_queue.php หาเลขสูงสุดจาก created_at ของวันนี้ · คิว 31 ของ 28/10 → วันนี้ออก 32 2569-10-29)
            if ((string) $this->date !== \App\Models\VisitAppointment::todayBe()) { return; }
            $issuedToday = $q->getConnection()
                ->selectOne('SELECT DATE(created_at) = CURDATE() AS t FROM queues WHERE id = ?', [$q->id]);
            if (! $issuedToday || ! (int) $issuedToday->t) { return; }

            if ($q->status === 'completed') {
                $q->update(['status' => 'waiting']);   // ยังไม่กลับบ้าน -> ต้องอยู่บนจอ
            }

            // คนไข้ย้ายห้อง -> คิวต้องย้ายตาม ไม่งั้นคิวค้างอยู่ห้องเดิมทั้งที่คนเดินไปห้องใหม่แล้ว
            // ย้ายแล้วตั้งเป็น "รอเรียก" ของห้องใหม่ และดับไฟเรียกของห้องเดิม
            $room = self::$statusQueueRoom[(int) $this->status] ?? null;
            if ($room && (int) $q->room_number !== $room) {
                $q->update([
                    'room_number' => $room,
                    'status'      => 'waiting',
                    'is_calling'  => 0,
                ]);
                // เวลารอนับใหม่ที่ห้องใหม่ — โปรแกรมคิวกลางเองตอนกดย้ายห้องก็ตั้ง created_at = NOW() (call_queue.php)
                // เดิมฝั่งนี้ไม่ตั้ง จอห้องใหม่เลยนับเวลาต่อจากตอนออกบัตร คิวที่ส่งมาดูเหมือนรอนานเป็นชั่วโมง (คลินิกแจ้ง 2569-10-05)
                // ใช้ NOW() ของเซิร์ฟเวอร์คิวเอง ไม่ใช้เวลาของ Laravel — จอคำนวณ TIMESTAMPDIFF กับ NOW() ฝั่งนั้น
                $q->getConnection()->table($q->getTable())->where('id', $q->id)
                    ->update(['created_at' => $q->getConnection()->raw('NOW()')]);
            }
        } catch (\Throwable $e) {
            // คิวกลางคนละฐานข้อมูล ถ้าล่มต้องไม่ทำให้การบันทึกของพยาบาลพัง
            \Illuminate\Support\Facades\Log::warning('ซิงก์คิวกลางไม่สำเร็จ: ' . $e->getMessage(), ['visit_id' => $this->id]);
        }
    }

    // ================================================================
    // ผลตรวจย่อของ visit นี้ (HIV / ซิฟิลิส) — หมอขอไว้ดูในตารางประวัติ (2026-08-05)
    // ผลแล็บต้องผ่านกติกา Lab::isResultVisible() เสมอ: ที่ห้องแล็บยังไม่อนุมัติ ห้ามโผล่
    // ================================================================

    /**
     * ผลแล็บของ visit นี้ตาม method (7=TP syphilis, 8=RPR ฯลฯ) — คืน '' ถ้าไม่มี/ยังไม่อนุมัติ
     * query Lab ตรง ๆ ไม่ใช้ $this->labs เพราะ relation ชื่อพหูพจน์แต่เป็น hasOne (กำกวม)
     */
    public function labResultByMethod(int $method): string
    {
        $out = [];
        foreach (Lab::where('visit_id', $this->id)->get() as $lab) {
            if (!$lab->isResultVisible()) { continue; }     // ยังไม่อนุมัติ = ยังไม่ใช่ผลที่เชื่อได้
            foreach (LabSub::where('lab_id', $lab->id)->where('method', $method)->get() as $sub) {
                $r = trim((string) $sub->result);
                if ($r !== '') { $out[] = $r; }
            }
        }
        return implode(', ', array_unique($out));
    }

    /**
     * ผล HIV = Anti-HIV (method 6) ที่ตรวจใน "แล็บของเรา" เท่านั้น
     *
     * ตั้งใจไม่ใช้ visits.hiv_sti_test_resule เพราะช่องนั้นคือผลที่คนไข้แจ้งมาจากที่อื่น
     * (เจ้าหน้าที่คีย์เอง) — ผู้ใช้สั่งให้ยึดผลจากแล็บเราก่อน 2026-08-05
     * เคสที่เจอจริง: แล็บเรามีผล Anti-HIV อยู่ แต่ช่องที่คีย์เองว่าง
     * คอลัมน์ HIV เลยไม่ขึ้นทั้งที่ตรวจกับเราจริง
     */
    public function hivResultText(): string
    {
        return $this->labResultByMethod(6);
    }

    /** ผล HIV ที่คนไข้แจ้งมาจากที่อื่น (ช่อง Hiv Test Result ที่เจ้าหน้าที่คีย์) */
    public function hivResultReportedText(): string
    {
        $v = (int) ($this->hiv_sti_test_resule ?? 0);
        return self::$hivtestresultOption[$v] ?? '';
    }

    /** ผลซิฟิลิส = TP syphilis (method 7) ของ visit นี้ */
    public function syphilisResultText(): string
    {
        return $this->labResultByMethod(7);
    }

    /** สีป้ายผลตรวจ: บวก/ผิดปกติ = แดง, ลบ = เขียว, อื่น ๆ = เทา */
    public static function resultBadgeClass(string $text): string
    {
        $t = mb_strtolower(trim($text));
        if ($t === '') { return ''; }
        // เช็ค non-reactive/not detected ก่อน ไม่งั้นจะไปเข้าเงื่อนไข reactive/detected
        if (preg_match('/non[\s-]?reactive|not[\s-]?detected|negative|neg\b/u', $t)) { return 'bg-success'; }
        if (preg_match('/positive|pos\b|reactive|detected|1:\d+/u', $t))             { return 'bg-danger'; }
        return 'bg-secondary';
    }

    // ================================================================
    // "ผ่านมาแล้วกี่วัน/เดือน/ปี" นับจากวันนี้ — หมอขอไว้ดูในตารางประวัติ (2026-08-03)
    // date เก็บเป็น พ.ศ. อาจเป็น Y-m-d (ดิบ) หรือ d/m/Y (ผ่าน formatDBBack แล้ว)
    // ห้ามใช้ strtotime กับปี พ.ศ. — ต้องแปลงเป็น ค.ศ. เองก่อน
    // ================================================================

    /** วันที่ของ visit นี้เป็น Carbon (ค.ศ.) — คืน null ถ้าอ่านไม่ได้ */
    public function visitDateAd(): ?\Carbon\Carbon
    {
        $raw = trim((string) ($this->date ?? ''));
        if ($raw === '' || $raw === '0000-00-00') { return null; }

        $y = $m = $d = null;
        if (strpos($raw, '/') !== false) {
            $p = explode('/', $raw);
            if (count($p) === 3) { [$d, $m, $y] = [(int) $p[0], (int) $p[1], (int) $p[2]]; }
        } elseif (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $raw, $mm)) {
            [$y, $m, $d] = [(int) $mm[1], (int) $mm[2], (int) $mm[3]];
        }
        if (!$y || !$m || !$d) { return null; }

        if ($y > 2400) { $y -= 543; }                     // พ.ศ. -> ค.ศ.
        try { return \Carbon\Carbon::create($y, $m, $d, 0, 0, 0); } catch (\Throwable $e) { return null; }
    }

    /**
     * ข้อความ "ผ่านมาแล้วเท่าไร" แบบครบหน่วย: 1 ปี 2 เดือน 15 วัน (ตามที่แพทย์ขอ)
     * ข้ามหน่วยที่เป็นศูนย์ เช่น 2 เดือน 3 วัน / 1 ปี 5 วัน
     * ตั้งใจไม่ใช้ diffForHumans() เพราะได้ "1 year ago" ปนอังกฤษ และปัดเป็นหน่วยเดียวหยาบเกินไป
     */
    public function elapsedText(): string
    {
        $when = $this->visitDateAd();
        if (!$when) { return ''; }

        $today = \Carbon\Carbon::today();
        if ($when->gt($today)) { return 'ล่วงหน้า'; }      // วันที่อนาคต (คีย์ผิด) ไม่ต้องเดา

        $days = (int) $when->diffInDays($today, true);
        if ($days === 0) { return 'วันนี้'; }
        if ($days === 1) { return 'เมื่อวาน'; }

        // diff() ให้ ปี/เดือน/วัน ที่หักกันแล้ว (ไม่ใช่ผลรวมของแต่ละหน่วย)
        $d = $when->diff($today);
        $parts = [];
        if ($d->y) { $parts[] = $d->y . ' ปี'; }
        if ($d->m) { $parts[] = $d->m . ' เดือน'; }
        if ($d->d) { $parts[] = $d->d . ' วัน'; }

        return $parts ? implode(' ', $parts) : 'วันนี้';
    }

    /**
     * สีป้าย "ผ่านมาแล้ว" — ยิ่งนานยิ่งจาง เพื่อให้กวาดตาเห็นว่าครั้งไหนเพิ่งมา
     * ใช้เฉพาะคลาสที่ Bootstrap 5.1.3 มี — bg-*-subtle / text-*-emphasis เป็นของ 5.3
     * ถ้าเผลอใช้จะได้ป้ายขาวบนขาว มองไม่เห็นทั้งอัน
     */
    public function elapsedBadgeClass(): string
    {
        $when = $this->visitDateAd();
        if (!$when) { return 'bg-light text-dark'; }

        $days = (int) $when->diffInDays(\Carbon\Carbon::today(), true);
        if ($days <= 7)   { return 'bg-success'; }              // เพิ่งมา
        if ($days <= 90)  { return 'bg-primary'; }              // ใน 3 เดือน
        if ($days <= 365) { return 'bg-warning text-dark'; }    // ในปีนี้
        return 'bg-secondary';                                  // เกิน 1 ปี
    }

    // ================================================================
    // ตรวจสถานะการมาตามนัด — รองรับ "นัดซ้อน" (มีนัดค้างหลายใบพร้อมกัน)
    // คืน ['level' => ontime|overdue|future|none, 'html' => '...', 'count' => n]
    // วันที่เก็บเป็น พ.ศ. (Y-m-d) ในฐานข้อมูล; $this->date อาจเป็น d/m/Y (ถ้า formatDBBack แล้ว) หรือ Y-m-d
    // ไม่ใช้ strtotime กับวันที่ พ.ศ. (เพี้ยนง่าย) แปลงเองด้วย explode
    // ================================================================
    // $onlyToday = true : เตือนเฉพาะ visit ของ "วันนี้" (คนไข้มารับบริการจริง)
    //   ไม่เด้งตอนเปิดดู visit เก่าย้อนหลัง (default สำหรับ popup หน้าห้องตรวจ)
    public function appointmentAlert(bool $onlyToday = true): array
    {
        $none = ['level' => 'none', 'html' => '', 'count' => 0];

        // วันที่มาของ visit นี้ -> พ.ศ. Y-m-d
        $rawDate = $this->date ?? '';
        $currentBE = null;
        if (strpos($rawDate, '/') !== false) {
            $p = explode('/', $rawDate);
            if (count($p) === 3) { $currentBE = sprintf('%04d-%02d-%02d', $p[2], $p[1], $p[0]); }
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate)) {
            $currentBE = $rawDate;
        }
        if (!$currentBE || !$this->patient2) { return $none; }

        // ถ้าไม่ใช่ visit ของวันนี้ (เปิดดูย้อนหลัง) ไม่ต้องเด้งเตือน
        if ($onlyToday) {
            $todayBE = \App\Utillity::AD2BE(now()->format('Y-m-d'));
            if ($currentBE !== $todayBE) { return $none; }
        }

        $allVisits = $this->patient2->visits2()
            ->orderBy('date', 'asc')
            ->get(['id', 'date', 'appointment', 'appointment_reason']);

        $visitDates = $allVisits->pluck('date')->filter()->all();

        // นัดทุกใบจากตาราง visit_appointments (นัดได้หลายวันต่อ visit) + ช่องนัดเดิมของ visit (ข้อมูลยุคก่อนมีตาราง)
        // เดิมดูแค่ช่อง visits.appointment ซึ่งเก็บนัดใบแรกใบเดียว — นัดใบที่ 2 ขึ้นไปจึงไม่เคยเตือน (คลินิก TB แจ้ง 2569-10-14)
        $prevIds = $allVisits->filter(fn ($pv) => $pv->id !== $this->id && $pv->date <= $currentBE)->pluck('id');
        $apptRows = [];
        foreach ($allVisits as $pv) {
            if ($pv->id === $this->id || $pv->date > $currentBE) { continue; }
            if (! empty($pv->appointment) && $pv->appointment !== '0000-00-00') {
                $apptRows[] = (object) ['date' => $pv->date, 'appointment' => $pv->appointment, 'appointment_reason' => $pv->appointment_reason];
            }
        }
        if ($prevIds->isNotEmpty()) {
            foreach (\App\Models\VisitAppointment::whereIn('visit_id', $prevIds)->get(['appointment_date', 'reason']) as $a) {
                $apptRows[] = (object) ['date' => '', 'appointment' => $a->appointment_date, 'appointment_reason' => $a->reason];
            }
        }

        $relevant = [];
        foreach ($apptRows as $pv) {
            $appt = $pv->appointment;

            if ($appt === $currentBE) {
                $relevant[$appt] = ['date' => $appt, 'reason' => $pv->appointment_reason, 'status' => 'ontime'];
            } elseif ($appt > $currentBE) {
                if (!isset($relevant[$appt])) {
                    $relevant[$appt] = ['date' => $appt, 'reason' => $pv->appointment_reason, 'status' => 'future'];
                }
            } else {
                $fulfilled = false;
                foreach ($visitDates as $vd) {
                    if ($vd >= $appt && $vd < $currentBE) { $fulfilled = true; break; }
                }
                if (!$fulfilled && !isset($relevant[$appt])) {
                    $relevant[$appt] = ['date' => $appt, 'reason' => $pv->appointment_reason, 'status' => 'overdue'];
                }
            }
        }

        if (empty($relevant)) { return $none; }

        ksort($relevant);
        $statuses = array_column($relevant, 'status');

        if (in_array('ontime', $statuses, true)) {
            $level = 'ontime';
            $headline = "<span style='color: green; font-weight: bold;'>✅ ผู้ป่วยมาตรงนัด</span>";
        } elseif (in_array('overdue', $statuses, true)) {
            $level = 'overdue';
            $headline = "<span style='color: red; font-weight: bold;'>⚠️ ผู้ป่วยไม่ได้มาตามนัด (เลยวันนัด)</span>";
        } else {
            $level = 'future';
            $headline = "<span style='color: dodgerblue; font-weight: bold;'>⏳ ยังไม่ถึงวันนัด (มาก่อนนัด/มาด้วยเรื่องอื่น)</span>";
        }

        $lines = "";
        foreach ($relevant as $r) {
            $bp = explode('-', $r['date']);
            $dispDate = (count($bp) === 3) ? "{$bp[2]}/{$bp[1]}/{$bp[0]}" : $r['date'];
            $reason = trim($r['reason'] ?? '') !== '' ? $r['reason'] : 'ไม่ระบุ';

            $dayTxt = "";
            try {
                $apptC = \Carbon\Carbon::parse(\App\Utillity::BE2AD($r['date']));
                $curC  = \Carbon\Carbon::parse(\App\Utillity::BE2AD($currentBE));
                $diff  = (int) $apptC->diffInDays($curC, true);
                if ($r['status'] === 'future')  { $dayTxt = " (อีก {$diff} วัน)"; }
                if ($r['status'] === 'overdue') { $dayTxt = " (เลยมาแล้ว {$diff} วัน)"; }
                if ($r['status'] === 'ontime')  { $dayTxt = " (วันนี้)"; }
            } catch (\Throwable $e) { $dayTxt = ""; }

            $badge = [
                'ontime'  => "<span style='color: green;'>ตรงนัด</span>",
                'overdue' => "<span style='color: red;'>เลยนัด</span>",
                'future'  => "<span style='color: dodgerblue;'>ยังไม่ถึง</span>",
            ][$r['status']];

            $lines .= "<div style='margin-top:4px;'>{$badge}{$dayTxt} — <b>{$dispDate}</b><br>
                <span style='color: blue; font-size: 15px;'>{$reason}</span></div>";
        }

        $multi = count($relevant) > 1
            ? "<div style='color:#b8860b; font-size:13px; margin-top:2px;'>* มีนัดค้าง " . count($relevant) . " รายการ</div>"
            : "";

        return ['level' => $level, 'html' => "{$headline}{$multi}{$lines}", 'count' => count($relevant)];
    }
// ในไฟล์ Visit.php

public function visitLabs()
{
    // เชื่อมโยงไปหาตาราง Lab (เดาว่าชื่อ Model คือ VisitLab หรือ Lab)
    // กรุณาตรวจสอบว่า Model ชื่อ App\Models\VisitLab หรือ App\Models\Lab 
    // ถ้าไฟล์ Model ชื่อ VisitLab.php ให้ใช้ VisitLab::class ครับ
    return $this->hasMany(\App\Models\Lab::class, 'visit_id');
}
public function labs()
{
    return $this->hasOne(Lab::class, 'visit_id');
}
public function billings()
{
    // ชี้ไปที่ Model Billing โดยใช้ visit_id
    return $this->hasOne(Billing::class, 'visit_id');
}
// 1. ความสัมพันธ์ไปหาตาราง lab_bloods (1 Visit มี 1 ผลเลือด)
    public function labBlood()
    {
        // เช็คชื่อ Model LabBlood ด้วยนะครับว่าตรงกับในโฟลเดอร์ App\Models ไหม
        return $this->hasOne(\App\Models\LabBlood::class, 'visit_id', 'id');
    }

    // 2. ความสัมพันธ์ไปหาตาราง contact_people (1 Visit มีได้หลายคู่สัมผัส)
    public function contactPeople()
    {
        // เช็คชื่อ Model ด้วยนะครับ บางทีอาจจะตั้งชื่อว่า ContactPeople หรือ ContactPerson
        return $this->hasMany(\App\Models\ContactPerson::class, 'visit_id', 'id');
    }

    // เมื่อ visit ถูกตั้งสถานะ "กลับบ้าน" (6) ให้ปิดงานคิวที่ผูกไว้ในระบบเรียกคิวกลางไปด้วยเสมอ
    // (ครอบคลุมทุกจุดที่ set status=6 ไม่ว่าจะเป็น VisitController หรือ PharmacyController)
    /** สาเหตุการมา TMC ที่ระบบปรับให้เอง — ต้องตรงกับชื่อในตาราง tmc_reasons */
    public const TMC_REASON_VACCINE = 'ต้องการฉีดวัคซีน';
    public const TMC_REASON_CONSULT = 'รับการปรึกษา';

    /** visit นี้มีวัคซีนไหม: หมอสั่งวัคซีนในใบสั่งยา หรือลงว่าคลินิกให้ (ฉีดที่นี่/จ่ายไปทานเอง) — ประวัติจากที่อื่นไม่นับ */
    public function tmcHasVaccine(): bool
    {
        if (! $this->id) { return false; }
        $ordered = \App\Models\VisitMedicine::where('visit_id', $this->id)
            ->whereIn('medicine_id', \App\Models\Medicine::where('is_vaccine', 1)->select('id'))->exists();
        return $ordered || \App\Models\VisitVaccine::where('visit_id', $this->id)
            ->whereIn('source', [\App\Models\VisitVaccine::HERE, \App\Models\VisitVaccine::TAKE_HOME])->exists();
    }

    /**
     * ปรับข้อความสาเหตุการมา (คั่นจุลภาค) ตามว่ามีวัคซีนไหม
     * มีวัคซีน = ต้องมี "ต้องการฉีดวัคซีน" (พยาบาลลืมติ๊ก) · สาเหตุอื่นที่ติ๊กไว้คงไว้
     * $final (ตรวจจบแล้ว) และไม่มีวัคซีน = เอา "ต้องการฉีดวัคซีน" ออก ใส่ "รับการปรึกษา"
     */
    public static function tmcReasonAdjust(string $reason, bool $hasVaccine, bool $final): string
    {
        $list = array_values(array_filter(array_map('trim', explode(',', $reason)), fn ($v) => $v !== ''));
        if ($hasVaccine) {
            if (! in_array(self::TMC_REASON_VACCINE, $list, true)) { $list[] = self::TMC_REASON_VACCINE; }
        } elseif ($final) {
            $list = array_values(array_filter($list, fn ($v) => $v !== self::TMC_REASON_VACCINE));
            if (! in_array(self::TMC_REASON_CONSULT, $list, true)) { array_unshift($list, self::TMC_REASON_CONSULT); }
        }
        return implode(',', $list);
    }

    /** มีการสั่ง/ลงวัคซีนใน visit นี้ → ติ๊ก "ต้องการฉีดวัคซีน" ทันที (เรียกจาก VisitMedicine / VisitVaccine ตอนสร้าง) */
    public static function ensureTmcVaccineReason($visitId): void
    {
        $visit = $visitId ? self::find($visitId) : null;
        if (! $visit || (int) $visit->clinic !== 3) { return; }
        $fixed = self::tmcReasonAdjust((string) $visit->tmc_reason, true, false);
        if ($fixed !== (string) $visit->tmc_reason) {
            $visit->tmc_reason = $fixed;
            $visit->save();
        }
    }

    protected static function booted()
    {
        // Refer (ชื่อ รพ. ที่ส่งต่อ) แยกจากห้อง/สถานะ — คลินิกขอให้เป็นช่องติ๊กของตัวเอง (2569-10-08) · เก็บแบบตัดช่องว่าง ว่าง = ไม่ refer
        static::saving(function (Visit $visit) {
            if (self::referReady() && $visit->isDirty('refer_hospital')) {
                $v = trim((string) $visit->refer_hospital);
                $visit->refer_hospital = $v === '' ? null : mb_substr($v, 0, 255);
            }
        });

        // ใบวินิจฉัยถูกลบตาม visit ด้วย FK CASCADE ของฐานข้อมูล ซึ่งไม่เรียก hook ของโมเดล
        // รหัส ICD-10 ของใบพวกนั้น (icd10_entries) เลยค้าง — ลบเองก่อน (รีวิว 2569-09-30)
        static::deleting(function (Visit $visit) {
            \App\Models\Icd10Code::purgeFor(\App\Models\Diagnosi::icd10Owner(),
                \App\Models\Diagnosi::where('visit_id', $visit->id)->pluck('id')->all());

            // วัคซีนที่ฉีดใน visit นี้ไม่มีฐานข้อมูลบังคับลบตาม (ไม่มี FK) — เดิมค้างแล้วตัวนับเข็มนับเป็นเข็มที่ฉีดไปแล้ว
            // นัดเข็มถัดไปผิด (เจอ visit 12569 ที่ลบทิ้ง 2569-10-01) · ลบผ่านโมเดลเพื่อให้มีในประวัติการแก้ไข
            // "ประวัติเดิม/ที่อื่น" เป็นประวัติของคนไข้ ไม่ได้เกิดใน visit นี้ → เก็บไว้ แค่ตัดการผูกกับ visit
            foreach (\App\Models\VisitVaccine::where('visit_id', $visit->id)->get() as $vac) {
                if ((int) $vac->source === \App\Models\VisitVaccine::HISTORY) {
                    $vac->visit_id = null;
                    $vac->save();
                } else {
                    $vac->delete();
                }
            }
        });

        // TMC: ออกจากห้องตรวจไปการเงิน/ห้องยา/กลับบ้าน = ตรวจจบแล้ว → ปรับ "สาเหตุการมา" ให้ตรงกับที่ได้วัคซีนจริง
        // ไม่มีวัคซีนเลย = เอา "ต้องการฉีดวัคซีน" ออก ติ๊ก "รับการปรึกษา" แทน (คลินิกขอ 2569-11-03)
        // ไม่ทำตอนบันทึกหน้าตรวจ เพราะพยาบาลติ๊กไว้ก่อนหมอสั่ง — เอาออกตอนนั้นจะผิด
        static::updating(function (Visit $visit) {
            if ((int) $visit->clinic === 3 && $visit->isDirty('status') && in_array((int) $visit->status, [
                \App\Enums\VisitStatus::Finance->value, \App\Enums\VisitStatus::Pharmacy->value, \App\Enums\VisitStatus::Home->value,
            ], true)) {
                $fixed = self::tmcReasonAdjust((string) $visit->tmc_reason, $visit->tmcHasVaccine(), true);
                if ($fixed !== (string) $visit->tmc_reason) { $visit->tmc_reason = $fixed; }
            }
        });

        static::updating(function (Visit $visit) {
            if ($visit->isDirty('status') && \App\Enums\VisitStatus::Home->is($visit->status) && $visit->queue_system_id) {
                $queue = \App\Models\QueueSystemQueue::find($visit->queue_system_id);
                if ($queue && $queue->status !== 'completed') {
                    $queue->status = 'completed';
                    $queue->is_calling = 0;
                    $queue->save();
                }
            }
        });

        // ย้ายห้อง / คนไข้ใหม่ → บอกจอห้องตรวจ การเงิน ห้องยา ให้ดึงรายชื่อใหม่ทันที (ไม่ต้องรอรอบ 10 วิ)
        static::saved(function (Visit $visit) {
            if ($visit->wasRecentlyCreated || $visit->wasChanged('status')) {
                \App\Support\Realtime::signal('visit', [
                    'from' => $visit->wasRecentlyCreated ? null : (int) $visit->getOriginal('status'),
                    'to' => (int) $visit->status,
                ]);
            }
        });
    }
}
