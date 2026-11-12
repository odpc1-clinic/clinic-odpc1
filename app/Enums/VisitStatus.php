<?php

namespace App\Enums;

use App\Models\Visit;

/**
 * visits.status = คนไข้อยู่ห้องไหน (2569-10-20)
 *
 * ตั้งชื่อแทนเลขลอย ๆ ในโค้ด — เลขนี้เป็น "รหัสภายใน" ไม่ใช่เลขห้องที่ติดหน้าห้อง (status 2 = ห้องตรวจชาย ป้ายหน้าห้องคือ "4")
 * และคำว่า status ใช้ซ้ำคนละความหมายหลายตาราง (patients.status = กลุ่มเสี่ยง, labs.status = สถานะผล) — เคยทำให้เกิดบั๊ก
 *
 * ไม่ได้ cast คอลัมน์เป็น enum โดยตั้งใจ: โค้ดเดิมหลายร้อยจุดเทียบ $visit->status กับตัวเลข ถ้า cast จะพังทั้งหมด
 * ใช้แบบ: if ((int) $visit->status === VisitStatus::Home->value) · ->where('status', VisitStatus::Pharmacy->value)
 * ชื่อห้องที่แสดงผล/เลขห้องคิว ยังเก็บที่ Visit::$statusOption / $statusQueueRoom ที่เดียว (enum อ่านจากตรงนั้น)
 * ห้ามเปลี่ยนเลขของ case ใด ๆ — ข้อมูลเก่าในฐานอ้างอิงเลขเหล่านี้
 */
enum VisitStatus: int
{
    case Registration = 1;   // ลงทะเบียน
    case ExamMale = 2;       // ห้องตรวจชาย (ป้าย 4)
    case Injection = 3;      // ห้องเจาะเลือด/ฉีดยา (ป้าย 6)
    case Pharmacy = 5;       // ห้องยา (คิว 10) — ก่อน 2569-09-03 คือ "ห้องยา/การเงิน" รวมกัน
    case Home = 6;           // กลับบ้าน = จบเคส
    case ExamTmc = 7;        // ห้องตรวจ 7 (TMC)
    case ExamFemale = 8;     // ห้องตรวจหญิง (ป้าย 5)
    case Finance = 9;        // ห้องการเงิน (คิว 11)
    case ExamTb = 10;        // ห้องตรวจวัณโรค (ป้าย 2)

    /** ชื่อห้องที่แสดงบนจอ */
    public function label(): string
    {
        return Visit::$statusOption[$this->value] ?? (string) $this->value;
    }

    /** เลขห้องบนจอเรียกคิวกลาง (ลงทะเบียน/กลับบ้าน ไม่มีห้องคิว = null) */
    public function queueRoom(): ?int
    {
        return Visit::$statusQueueRoom[$this->value] ?? null;
    }

    /** เทียบกับค่าดิบจากฐาน/ฟอร์ม (อาจเป็นสตริง "6" หรือ null) */
    public function is(mixed $raw): bool
    {
        return $raw !== null && $raw !== '' && (int) $raw === $this->value;
    }
}
