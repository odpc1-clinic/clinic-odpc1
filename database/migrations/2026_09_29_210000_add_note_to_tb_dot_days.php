<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * หมายเหตุรายวันของ DOT (2569-09-29)
 *
 * พยาบาลที่กำกับกินยาต้องจดเหตุการณ์ของวันนั้นไว้ (เช่น "ส่งคลิปช้า โทรตามแล้ว", "อาเจียนหลังกินยา")
 * เดิมไม่มีที่จด เก็บไว้ต่อวันคู่กับสัญลักษณ์ แล้วหมอเห็นในปฏิทินบัตร TB 01 เป็น hover
 *
 * วันที่มีแค่หมายเหตุแต่ยังไม่ได้ลงสัญลักษณ์ก็ต้องเก็บได้ → mark เป็น null ได้อยู่แล้ว
 */
class AddNoteToTbDotDays extends Migration
{
    public function up()
    {
        Schema::table('tb_dot_days', function (Blueprint $table) {
            $table->string('note', 255)->nullable()->after('mark');
        });
    }

    public function down()
    {
        Schema::table('tb_dot_days', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
}
