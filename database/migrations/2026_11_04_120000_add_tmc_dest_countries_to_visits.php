<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ประเทศปลายทาง TMC แบบเลือกจากรายการ (คลินิกขอ 2569-11-04 — อยากกรองข้อมูลตามประเทศ)
 *
 * เดิมมีแค่ tmc_destinations เป็นข้อความอิสระ พิมพ์ปนไทย/อังกฤษ/สะกดต่างกัน (kenya, เคนยา, ฺBrazil)
 * กรองหรือนับตามประเทศไม่ได้ · ข้อความเดิมยังเก็บไว้เป็น "เมือง / รายละเอียด"
 *
 * tmc_dest_countries  รหัสประเทศ 3 ตัว (db_country.country_id) คั่นด้วยจุลภาค เช่น "KEN,TZA"
 *                     ใช้ FIND_IN_SET กรองได้ · ไปหลายประเทศก็เลือกได้หลายตัว
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('visits', 'tmc_dest_countries')) { return; }
        Schema::table('visits', function (Blueprint $t) {
            $t->string('tmc_dest_countries', 255)->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('visits', 'tmc_dest_countries')) { return; }
        Schema::table('visits', function (Blueprint $t) {
            $t->dropColumn('tmc_dest_countries');
        });
    }
};
