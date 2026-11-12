<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * visits.lmp_days = จำนวนวันนับจากวันแรกของ LMP ถึงวันที่มาตรวจ (1-6 หรือ "7+")
 * เก็บแยกจาก LMP (วันที่) เพราะบางครั้งคนไข้จำวันที่แน่นอนไม่ได้ แต่บอกจำนวนวันได้
 */
class AddLmpDaysToVisits extends Migration
{
    public function up()
    {
        Schema::table('visits', function (Blueprint $table) {
            if (!Schema::hasColumn('visits', 'lmp_days')) {
                $table->string('lmp_days', 5)->nullable()->after('LMP');
            }
        });
    }

    public function down()
    {
        Schema::table('visits', function (Blueprint $table) {
            if (Schema::hasColumn('visits', 'lmp_days')) $table->dropColumn('lmp_days');
        });
    }
}
