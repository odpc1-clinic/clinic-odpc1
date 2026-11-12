<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * แผนนัดฉีดวัคซีนที่แพทย์วางไว้ล่วงหน้า (TMC)
 *
 * ต่างจาก visit_vaccines.next_due_date ตรงที่อันนั้นเกิด "หลังฉีดแล้ว" (1 นัดต่อวัคซีน)
 * ส่วนอันนี้คือแผนทั้งคอร์สที่หมอวางตั้งแต่วันแรก (เช่น พิษสุนัขบ้า วัน 0/3/7/14/28)
 * เก็บเป็น JSON เพราะเป็นรายการสั้น (ไม่เกิน 5 นัด) ที่อ่าน/เขียนพร้อม visit เสมอ
 * รูปแบบ: [{"date":"15/08/2569","vaccines":["Rabies","Tetanus"]}, ...]
 */
class AddTmcVaccinePlanToVisits extends Migration
{
    public function up()
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->text('tmc_vaccine_plan')->nullable()->after('tmc_plan');
        });
    }

    public function down()
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn('tmc_vaccine_plan');
        });
    }
}
