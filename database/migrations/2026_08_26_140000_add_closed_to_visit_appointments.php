<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ปิดนัดที่ไม่ต้องมาแล้ว (2569-08-26)
 *
 * ปัญหา: คนไข้มาก่อนวันนัดเพราะวันนัดไม่ว่าง แล้วไม่มีใครไปปิดนัดเดิม
 * นัดจึงค้างอยู่ ไปโผล่ในรายชื่อนัด นับเป็น "ยังไม่มา" และจะยิง LINE เตือนคนที่มาแล้ว
 * วัดจากข้อมูลจริง 12 เดือน: มาก่อนนัด 209 ครั้ง ในนั้น 149 ครั้ง (71%) ไม่ได้มาในวันนัดอีกเลย
 *
 * ทำไมไม่ให้ระบบปิดให้เอง: "มาก่อนนัด" มีสองความหมายที่ดูจากข้อมูลแยกไม่ออก —
 * มาทำเรื่องเดียวกับที่นัดไว้ (นัดจบแล้ว) กับมาด้วยเรื่องอื่น (นัดเดิมยังต้องมา)
 * คนที่รู้คือพยาบาลห้องตรวจ ระบบจึงแค่ถามให้กดครั้งเดียว ไม่เดาแทน
 *
 * ไม่ลบแถวทิ้ง เพราะต้องตรวจย้อนหลังได้ว่าใครปิดเมื่อไหร่ด้วยเหตุผลอะไร
 */
class AddClosedToVisitAppointments extends Migration
{
    public function up()
    {
        Schema::table('visit_appointments', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable()->after('confirm_sent_at');

            // done_early = มาทำก่อนแล้ว · cancelled = ยกเลิกนัด · no_show = ไม่มาและไม่ตามต่อ
            $table->string('closed_reason', 20)->nullable()->after('closed_at');

            $table->unsignedBigInteger('closed_by')->nullable()->after('closed_reason');

            // visit ที่ทำให้ปิดนัดนี้ (ครั้งที่คนไข้มาก่อนกำหนด) — ไว้ตามรอยว่าปิดเพราะการมาครั้งไหน
            $table->unsignedBigInteger('closed_visit_id')->nullable()->after('closed_by');

            // ตัวส่ง LINE และตัวเตือนหน้าจอค้นด้วย "ยังไม่ปิด + วันไหน" เป็นหลัก
            $table->index(['closed_at', 'appointment_date'], 'va_open_date_idx');
        });
    }

    public function down()
    {
        Schema::table('visit_appointments', function (Blueprint $table) {
            $table->dropIndex('va_open_date_idx');
            $table->dropColumn(['closed_at', 'closed_reason', 'closed_by', 'closed_visit_id']);
        });
    }
}
