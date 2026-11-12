<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * สมุดทะเบียนติดตามผู้ป่วย 5 โรคหลัก + คู่สัมผัส (ช่องที่พยาบาลกรอกเอง)
 * ช่องอื่น (การติดตามคู่สัมผัส/ผล HIV/วันนัด) อยู่ในตาราง visits
 */
class ContactSti extends Model
{
    protected $table = 'contact_sti';

    protected $fillable = ['visit_id', 'patient_type', 'fu_status', 'sex_date', 'partner_type', 'note', 'hiv_service'];

    /** disease_state ที่ดึงเข้าทะเบียน = ผู้ป่วยใหม่ / เกิดโรคซ้ำ */
    public const CASE_STATES = [1, 2];

    /** ประเภทผู้ป่วย (ตาม sheet "รายละเอียด") */
    public static $patientTypeOption = ['MSM', 'MSW', 'FSW', 'PWID', 'Migrants', 'Prisoner', 'เยาวชน', 'ประชาชนทั่วไป'];

    /** การตรวจ HIV (บริการ) — "ตรวจแล้ว" จะเปิดช่องกรอกผล+วันที่ */
    public static $hivServiceOption = ['on Prep', 'on ARV', 'SDR', 'Advice', 'จะไปตรวจตามสิทธิ์', 'ตรวจแล้ว', 'อื่นๆ'];

    /** สถานะ F/U */
    public static $fuStatusOption = ['Loss F/U', 'F/U แล้ว', 'No F/U', 'โทรตาม หายแล้ว', 'F/U ที่ซายูริ'];

    /** ชนิดคู่สัมผัสใน 3 เดือน */
    public static $partnerTypeOption = ['สามีหรือภรรยา', 'คู่นอนประจำ', 'คู่นอนชั่วคราว', 'ผู้ให้บริการทางเพศ', 'ลูกค้า', 'คนไม่รู้จัก', 'one night stand'];

    /** patient.status (STI) => ประเภทในทะเบียน ContactSTI (เดาให้ก่อน พยาบาลปรับได้) */
    public static $statusToType = [
        1 => 'MSM',            // MSM/MSW/TG
        2 => 'FSW',            // พนักงานบริการ
        3 => 'Migrants',       // แรงงานข้ามชาติ
        4 => 'Prisoner',       // ผู้ป่วยเรือนจำ
        5 => 'เยาวชน',
        6 => 'ประชาชนทั่วไป',
        7 => 'PWID',           // ผู้ใช้สารเสพติด
    ];

    /** diagnoses.disease => ชื่อโรคในทะเบียน (5 โรคหลัก + LGV) */
    public static $diseaseToName = [
        2  => 'หนองในแท้',
        3  => 'หนองในเทียม',
        19 => 'หนองในเทียม',
        1  => 'ซิฟิลิส',
        5  => 'แผลริมอ่อน',
        4  => 'กามโรคของต่อมและท่อน้ำเหลือง',
    ];

    /** id ของโรคที่เข้าเกณฑ์ทะเบียนนี้ */
    public static function diseaseIds(): array
    {
        return array_keys(self::$diseaseToName);
    }

    // ===================== เติมคอลัมน์ "การตรวจ HIV" อัตโนมัติ =====================
    // ทั้ง visits.last_hiv_syphilis และ visits.hiv_sti_test_date เป็น text ที่พยาบาลพิมพ์อิสระ
    // ข้อมูลจริงกระจายมาก (24/7/69, 1 ด, 9 เดือนก่อน, พค. 69, 1 ปี, on ARV รพ.นครพิงค์)
    // จึงต้องอ่านแบบยืดหยุ่น และ "อ่านไม่ออก = ไม่เดา" (เว้นช่องว่างไว้ให้คนกรอกเอง)

    /** เดือนไทยย่อ/เต็ม -> เลขเดือน */
    private static $thMonths = [
        'มกราคม' => 1, 'กุมภาพันธ์' => 2, 'มีนาคม' => 3, 'เมษายน' => 4,
        'พฤษภาคม' => 5, 'มิถุนายน' => 6, 'กรกฎาคม' => 7, 'สิงหาคม' => 8,
        'กันยายน' => 9, 'ตุลาคม' => 10, 'พฤศจิกายน' => 11, 'ธันวาคม' => 12,
        'มค' => 1, 'กพ' => 2, 'มีค' => 3, 'เมย' => 4, 'พค' => 5, 'มิย' => 6,
        'กค' => 7, 'สค' => 8, 'กย' => 9, 'ตค' => 10, 'พย' => 11, 'ธค' => 12,
    ];

    /**
     * ตรวจว่าคนไข้ on PrEP / on ARV อยู่หรือไม่ จากข้อความประวัติ 2 ช่อง
     * (on ARV มักถูกพิมพ์ไว้ในช่อง hiv_sti_test_date ไม่ใช่ last_hiv_syphilis — ดูข้อมูลจริง)
     * คืน 'on ARV' | 'on Prep' | null  — ต้องตรงกับ $hivServiceOption
     */
    public static function detectHivStatus(?string $lastHivSyphilis, ?string $hivTestDate): ?string
    {
        $text = trim((string) $lastHivSyphilis) . ' | ' . trim((string) $hivTestDate);
        if (trim($text) === '|') { return null; }

        // "คู่ on Prep" = คู่นอนของเขา on PrEP ไม่ใช่ตัวคนไข้ — ห้ามนับ
        $selfText = preg_replace('/คู่\s*(นอน)?\s*on\s*p\s*rep/iu', ' ', $text);

        // ARV มาก่อน: on ARV = ติดเชื้อแล้วกินยาต้าน ซึ่งสำคัญกว่า PrEP
        if (preg_match('/on\s*ARV/iu', $selfText)) { return 'on ARV'; }
        if (preg_match('/on\s*p\s*rep/iu', $selfText)) { return 'on Prep'; }
        return null;
    }

    /**
     * อายุของผลตรวจ HIV ครั้งก่อน (เป็นเดือน) จากข้อความอิสระ
     * @param  string|null $raw          ข้อความในช่อง hiv_sti_test_date
     * @param  string|null $visitDateBe  วันที่ visit (พ.ศ. Y-m-d) ใช้เป็นจุดอ้างอิง
     * @return float|null                null = อ่านไม่ออก (ไม่นับว่าตรวจแล้ว -> เว้นช่องว่าง)
     */
    public static function testAgeMonths(?string $raw, ?string $visitDateBe): ?float
    {
        $s = trim((string) $raw);
        if ($s === '') { return null; }

        // จุดอ้างอิง = วันที่ visit (แปลง พ.ศ. -> ค.ศ.) ถ้าไม่มีก็ใช้วันนี้
        $ref = null;
        if ($visitDateBe && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $visitDateBe, $m)) {
            $y = (int) $m[1];
            if ($y > 2400) { $y -= 543; }
            try { $ref = \Carbon\Carbon::create($y, (int) $m[2], (int) $m[3]); } catch (\Throwable $e) { $ref = null; }
        }
        if (!$ref) { $ref = \Carbon\Carbon::now(); }

        // 1) วันที่เต็ม d/m/ปี ที่ต้นข้อความ — "24/7/69", "10/4/2569", "3/7/69@CareMat"
        if (preg_match('#^\s*(\d{1,2})/(\d{1,2})/(\d{2,4})#u', $s, $m)) {
            $d = (int) $m[1]; $mo = (int) $m[2]; $y = (int) $m[3];
            if (strlen($m[3]) === 2) { $y += 2500; }        // "69" = 2569
            if ($y > 2400) { $y -= 543; }                    // พ.ศ. -> ค.ศ.
            if ($mo >= 1 && $mo <= 12 && $d >= 1 && $d <= 31) {
                try {
                    $when = \Carbon\Carbon::create($y, $mo, min($d, 28));
                    $diff = (int) $when->diffInDays($ref, false) / 30.44;
                    return $diff >= 0 ? $diff : null;        // วันที่อนาคต = ผิด ไม่เดา
                } catch (\Throwable $e) { /* ตกไปลองแบบอื่น */ }
            }
        }

        // 2) ระยะเวลาแบบไทย — "1 ด", "2+ ด", "9 เดือนก่อน", "1 ปี", "1 wk"
        if (preg_match('/(\d+)\s*\+?\s*(ด|เดือน)/u', $s, $m))        { return (float) $m[1]; }
        if (preg_match('/(\d+)\s*\+?\s*(ปี|ป\.)/u', $s, $m))          { return (float) $m[1] * 12; }
        if (preg_match('/(\d+)\s*\+?\s*(wk|week|สัปดาห์|อาทิตย์)/iu', $s, $m)) { return (float) $m[1] / 4.345; }

        // 3) เดือนไทย + ปี — "พค. 69", "มค.66", "ปลาย มิย. 69", "ธ.ค.68"
        //    ตัดจุด/ช่องว่างในชื่อเดือนออกก่อน (พ.ค. -> พค) แล้วหาเลขปีที่ตามมา
        $flat = str_replace(['.', ' '], '', $s);
        foreach (self::$thMonths as $name => $mo) {
            $pos = mb_strpos($flat, $name);
            if ($pos === false) { continue; }
            $after = mb_substr($flat, $pos + mb_strlen($name));
            if (!preg_match('/^(\d{2,4})/u', $after, $ym)) { continue; }
            $y = (int) $ym[1];
            if (strlen($ym[1]) === 2) { $y += 2500; }
            if ($y > 2400) { $y -= 543; }
            try {
                $when = \Carbon\Carbon::create($y, $mo, 15);   // กลางเดือน (ไม่รู้วันแน่นอน)
                $diff = (int) $when->diffInDays($ref, false) / 30.44;
                return $diff >= 0 ? $diff : null;
            } catch (\Throwable $e) { return null; }
        }

        return null;   // อ่านไม่ออก
    }

    public function visit()
    {
        return $this->belongsTo(Visit::class, 'visit_id');
    }
}
