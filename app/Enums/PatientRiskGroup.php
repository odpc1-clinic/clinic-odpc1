<?php

namespace App\Enums;

use App\Models\Patient;

/**
 * patients.status = กลุ่มเสี่ยงของคนไข้ (2569-10-20)
 *
 * ระวัง: คอลัมน์ชื่อ status เหมือน visits.status (ห้อง) และ labs.status (สถานะผล) แต่คนละความหมายทั้งหมด
 * ในรายงานบางจุด query ดึง patients.status มาในแถวที่ชื่อตัวแปร $lab — ตัวแปรชื่อ lab แต่ค่าเป็นกลุ่มเสี่ยงคนไข้
 * ห้ามเปลี่ยนเลข case — ข้อมูลเก่าอ้างอิงเลขเหล่านี้ · ชื่อที่แสดงผลอยู่ที่ Patient::$statusOption
 */
enum PatientRiskGroup: int
{
    case MsmTg = 1;       // MSM/MSW/TG
    case SexWorker = 2;   // พนักงานบริการ
    case Migrant = 3;     // แรงงานข้ามชาติ
    case Prisoner = 4;    // ผู้ป่วยเรือนจำ
    case Youth = 5;       // เยาวชน
    case General = 6;     // ประชาชนทั่วไป
    case DrugUser = 7;    // ผู้ใช้สารเสพติด

    /** อาชีพ "อาชีพพิเศษ" (Patient::$occupationOption 10) — ใช้แยก MSW ออกจาก MSM */
    public const OCCUPATION_SPECIAL = 10;

    public function label(): string
    {
        return Patient::$statusOption[$this->value] ?? (string) $this->value;
    }

    /**
     * กลุ่มในตารางรายงานโรค/Lab (FSW · MSW · MSM · IDU · แรงงานข้ามชาติ · เยาวชน · อื่น ๆ)
     * เดิมเขียนซ้ำ 3 ที่ใน ReportController — รวมไว้ที่นี่ ผลต้องเท่าเดิมทุกกรณี จึงคงการเทียบแบบหลวม (==) ไว้
     * (ค่าในฐานอาจเป็นสตริง/NULL)
     * MSW = พนักงานบริการเพศชาย + กลุ่ม MSM/MSW/TG ที่อาชีพพิเศษ (คลินิกขอ 2569-11-02 — เดิมพนักงานบริการชายไปตกที่ FSW)
     */
    public static function reportBucket($status, $sex, $occupation): string
    {
        if ($status == self::SexWorker->value) { return $sex == 1 ? 'msw' : 'fsw'; }
        if ($status == self::MsmTg->value && $sex == 1 && $occupation == self::OCCUPATION_SPECIAL) { return 'msw'; }
        if ($status == self::MsmTg->value && $sex == 1 && $occupation != self::OCCUPATION_SPECIAL) { return 'msm'; }
        if ($status == self::DrugUser->value) { return 'idu'; }
        if ($status == self::Migrant->value) { return 'migrant'; }
        if ($status == self::Youth->value) { return 'youth'; }
        return 'other';
    }

    /** ชื่อกลุ่มของ reportBucket() สำหรับไฟล์ Excel */
    public const BUCKET_LABELS = [
        'fsw' => 'พนักงานบริการหญิง (FSW)', 'msw' => 'พนักงานบริการชาย (MSW)', 'msm' => 'ชายรักชาย (MSM)',
        'idu' => 'ผู้ใช้ยาเสพติดฉีด (IDU)', 'migrant' => 'แรงงานข้ามชาติ', 'youth' => 'เยาวชน', 'other' => 'อื่นๆ',
    ];
}
