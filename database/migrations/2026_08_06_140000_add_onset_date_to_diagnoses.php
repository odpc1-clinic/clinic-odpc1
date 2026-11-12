<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * วันที่เริ่มป่วย (onset) ต่อการวินิจฉัยหนึ่งรายการ
 *
 * รายงาน 506 ต้องส่งช่อง DATESICK ซึ่งเดิมใช้ "วันที่มารับบริการ" แทนไปก่อน
 * ทำให้วันเริ่มป่วยเท่ากับวันที่มาตรวจเสมอ ซึ่งไม่ตรงความจริง (ผู้ใช้ขอ 2569-08-06)
 *
 * เก็บเป็น พ.ศ. Y-m-d เหมือนวันที่อื่นในระบบ (varchar เพราะปี พ.ศ. เกินช่วงของ DATE)
 */
class AddOnsetDateToDiagnoses extends Migration
{
    public function up()
    {
        Schema::table('diagnoses', function (Blueprint $table) {
            $table->string('onset_date', 10)->nullable()->after('disease_state_other');
        });
    }

    public function down()
    {
        Schema::table('diagnoses', function (Blueprint $table) {
            $table->dropColumn('onset_date');
        });
    }
}
