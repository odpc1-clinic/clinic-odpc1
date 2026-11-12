<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ระยะ/เหตุผล DOT ของแต่ละวัน — เก็บไว้ออกรายงาน (พยาบาลขอ 2569-09-30)
 *
 * tb_registrations.dot_reason เป็นค่า "ปัจจุบัน" ค่าเดียว เปลี่ยนเมื่อไหร่ของเดิมหายหมด
 * รายงานอย่าง "เดือนนี้ DOT ระยะ MDR กี่ราย กี่วัน" จึงต้องประทับค่าไว้กับวันที่ลง DOT ทุกวัน
 *
 * แถวเดิม (เริ่มลงเดือน ก.ย. 69) เติมจากค่าปัจจุบันของบัตร — ยังไม่เคยเปลี่ยนระยะมาก่อน จึงถือว่าตรง
 */
class AddDotReasonToTbDotDays extends Migration
{
    public function up()
    {
        Schema::table('tb_dot_days', function (Blueprint $table) {
            $table->string('dot_reason', 100)->nullable()->after('phase');
        });

        DB::statement('UPDATE tb_dot_days d JOIN tb_registrations r ON r.id = d.tb_registration_id
                       SET d.dot_reason = NULLIF(TRIM(r.dot_reason), \'\')');
    }

    public function down()
    {
        Schema::table('tb_dot_days', function (Blueprint $table) {
            $table->dropColumn('dot_reason');
        });
    }
}
