<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * จำนวนวันที่จ่ายยาในแต่ละเดือน — ใช้คิดจำนวนเม็ดบนสติ๊กเกอร์ยา
 *
 * ยาวัณโรคเบิกจากกองวัณโรค ไม่ได้ตัดสต็อกคลินิกและไม่คิดเงิน
 * ระบบจึงเก็บแค่ "จ่ายกี่วัน" ไว้คำนวณจำนวนที่จ่ายและพิมพ์สติ๊กเกอร์ (2569-09-28)
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('tb_monthly_doses', function (Blueprint $table) {
            $table->unsignedSmallInteger('dispensed_days')->nullable()->after('dispensed_date');
        });
    }

    public function down()
    {
        Schema::table('tb_monthly_doses', function (Blueprint $table) {
            $table->dropColumn('dispensed_days');
        });
    }
};
