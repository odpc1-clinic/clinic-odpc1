<?php

namespace App\Enums;

use App\Models\Lab;

/**
 * labs.status = สถานะใบ Lab (2569-10-20)
 *
 * คนละความหมายกับ visits.status (ห้อง) และ patients.status (กลุ่มเสี่ยง)
 * ห้ามเปลี่ยนเลข case — ข้อมูลเก่าอ้างอิงเลขเหล่านี้ · ชื่อที่แสดงผลอยู่ที่ Lab::$statusOption
 */
enum LabStatus: int
{
    case Sent = 1;                // ส่ง Lab
    case Done = 2;                // เสร็จ — หน้าห้อง Lab ซ่อนใบสถานะนี้โดยค่าเริ่มต้น
    case WaitRpr = 3;             // รอลงผล RPR
    case WaitCulturePcr = 4;      // รอลงผล Culture/PCR
    case WaitRprCulturePcr = 5;   // รอลงผล RPR+Culture/PCR
    case WaitCulture = 6;         // รอลงผล Culture
    case WaitPcr = 7;             // รอลงผล PCR
    case WaitPap = 8;             // รอลงผล Pap Smear
    case WaitHbsAb = 9;           // รอลงผล HBs Ab

    public function label(): string
    {
        return Lab::$statusOption[$this->value] ?? (string) $this->value;
    }

    public function is(mixed $raw): bool
    {
        return $raw !== null && $raw !== '' && (int) $raw === $this->value;
    }
}
