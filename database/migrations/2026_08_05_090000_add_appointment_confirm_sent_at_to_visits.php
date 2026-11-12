<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * กันส่ง "ข้อความยืนยันนัด" ซ้ำ
 *
 * ข้อความนี้ยิงตอนกดกลับบ้าน (status 6) ซึ่งกดซ้ำได้หลายรอบ
 * (ห้องยากดกลับบ้าน แล้วเรียกกลับมาแก้ แล้วกดใหม่ ฯลฯ) — ถ้าไม่จำไว้ คนไข้จะโดนส่งซ้ำทุกครั้ง
 * และโควตา LINE มีแค่ 300/เดือน
 *
 * ค่านี้ถูกล้างเมื่อ "วันนัดเปลี่ยน" (ดู VisitController::update) เพื่อให้ยืนยันนัดใหม่ได้อีกครั้ง
 */
class AddAppointmentConfirmSentAtToVisits extends Migration
{
    public function up()
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->timestamp('appointment_confirm_sent_at')->nullable()->after('reminder_sent_at');
        });
    }

    public function down()
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn('appointment_confirm_sent_at');
        });
    }
}
