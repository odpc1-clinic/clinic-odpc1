<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ลำดับการแสดง "สาเหตุการมาที่คลินิก" TMC + เพิ่ม "รับการปรึกษา" ขึ้นก่อน มาลาเรียท้ายสุด
 */
class AddSortToTmcReasons extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('tmc_reasons', 'sort')) {
            Schema::table('tmc_reasons', function (Blueprint $table) {
                $table->integer('sort')->default(5)->after('name'); // รายการที่พยาบาลเพิ่มใหม่ = 5 (กลางๆ)
            });
        }

        // เพิ่ม "รับการปรึกษา" (ถ้ายังไม่มี)
        DB::table('tmc_reasons')->updateOrInsert(
            ['name' => 'รับการปรึกษา'],
            ['sort' => 1, 'updated_at' => now(), 'created_at' => now()]
        );

        // ตั้งลำดับ: ปรึกษา(1) < ขึ้นที่สูง(3) < วัคซีน(4) < ... < มาลาเรีย(9 ท้ายสุด)
        $order = [
            'รับการปรึกษา'                => 1,
            'การขึ้นที่สูง/เมาการเดินทาง'  => 3,
            'ต้องการฉีดวัคซีน'             => 4,
            'มาลาเรียและการป้องกัน'        => 9,
        ];
        foreach ($order as $name => $sort) {
            DB::table('tmc_reasons')->where('name', $name)->update(['sort' => $sort]);
        }
    }

    public function down()
    {
        if (Schema::hasColumn('tmc_reasons', 'sort')) {
            Schema::table('tmc_reasons', function (Blueprint $table) {
                $table->dropColumn('sort');
            });
        }
        DB::table('tmc_reasons')->where('name', 'รับการปรึกษา')->delete();
    }
}
