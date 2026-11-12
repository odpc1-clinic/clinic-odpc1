<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ช่องซักประวัติที่พยาบาลขอเพิ่ม (2569-09-29)
 *   chief_complaint   = CC อาการสำคัญที่มา
 *   underlying_denied = ปฏิเสธโรคประจำตัว (ต่างจาก "ยังไม่ได้ถาม" ซึ่งคือว่างเปล่า)
 * ส่วน Hx ใช้ช่อง illness_history เดิมที่มีอยู่แล้ว
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('tb_screenings', function (Blueprint $table) {
            $table->string('chief_complaint', 255)->nullable()->after('visit_id');
            $table->boolean('underlying_denied')->nullable()->after('underlying_disease');
        });
    }

    public function down()
    {
        Schema::table('tb_screenings', function (Blueprint $table) {
            $table->dropColumn(['chief_complaint', 'underlying_denied']);
        });
    }
};
