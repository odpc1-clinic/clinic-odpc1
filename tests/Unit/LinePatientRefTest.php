<?php

namespace Tests\Unit;

use App\Services\LineMessagingService;
use Tests\TestCase;

/**
 * บรรทัดอ้างอิงตัวผู้รับบริการในข้อความ LINE
 *
 * ต้องเป็น HN เท่านั้น ห้ามเป็นชื่อ-สกุล — ข้อความ LINE เด้งขึ้นหน้าจอล็อก
 * ชื่อคู่กับชื่อคลินิกระบุตัวคนได้ทันที ส่วน HN คนที่ไม่มีตารางจับคู่มองแล้วไม่ได้อะไร
 *
 * เมธอด patientName() ที่เคยประกอบชื่อ-สกุลถูกลบทิ้งไปแล้ว (26 ส.ค. 2569)
 * ไม่ใช่แค่เลิกเรียก เพื่อไม่ให้มีใครหยิบกลับมาใช้โดยไม่รู้เหตุผล
 */
class LinePatientRefTest extends TestCase
{
    public function test_returns_the_hn_prefixed()
    {
        $this->assertSame('HN 690000123', LineMessagingService::patientRef('690000123'));
    }

    /** HN ที่มีช่องว่างติดมาจากการกรอก ต้องไม่กลายเป็น "HN  690001" */
    public function test_trims_whitespace_around_the_hn()
    {
        $this->assertSame('HN 690000123', LineMessagingService::patientRef('  690000123  '));
    }

    /**
     * ไม่มี HN ให้คืนค่าว่าง เพื่อให้ผู้เรียกข้ามบรรทัดนั้นไปทั้งบรรทัด
     * ถ้าคืน "HN " ลอย ๆ คนไข้จะเห็นบรรทัดที่ไม่มีความหมายในข้อความ
     */
    public function test_stays_empty_when_there_is_no_hn()
    {
        foreach (['', '   ', null] as $empty) {
            $this->assertSame('', LineMessagingService::patientRef($empty));
        }
    }

    /**
     * กันการถอยกลับ: ไฟล์ที่ประกอบข้อความ LINE ต้องไม่หยิบชื่อ-สกุลมาใส่อีก
     * เช็คที่ซอร์สตรง ๆ เพราะการประกอบข้อความผูกกับฐานข้อมูล ทดสอบด้วยข้อมูลจริงไม่ได้
     */
    public function test_no_line_message_builds_a_patient_name()
    {
        $files = [
            base_path('app/Console/Commands/SendAppointmentReminders.php'),
            base_path('app/Services/AppointmentLineNotifier.php'),
        ];

        foreach ($files as $file) {
            $src = file_get_contents($file);
            $this->assertStringNotContainsString('patientName', $src, basename($file));
            $this->assertStringNotContainsString('->surname', $src, basename($file));
        }
    }
}
