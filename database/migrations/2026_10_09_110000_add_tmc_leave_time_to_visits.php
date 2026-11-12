<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * เวลากลับจริงของคนไข้ TMC หลังฉีดวัคซีน (คลินิกขอ 2569-10-09) — ปกติ = เวลาฉีด + 30 นาที (สังเกตอาการ)
 * แต่บางคนกลับก่อน ต้องบันทึกเวลาจริงได้ · เก็บ "HH:MM" ว่าง = ยังไม่ได้บันทึก (หน้าจอเสนอเวลาแนะนำให้)
 */
class AddTmcLeaveTimeToVisits extends Migration
{
    public function up()
    {
        Schema::table('visits', function (Blueprint $table) {
            if (! Schema::hasColumn('visits', 'tmc_leave_time')) {
                $table->string('tmc_leave_time', 5)->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('visits', function (Blueprint $table) {
            if (Schema::hasColumn('visits', 'tmc_leave_time')) {
                $table->dropColumn('tmc_leave_time');
            }
        });
    }
}
