<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ลักษณะสถานที่ TMC — แยก "ที่สูง" กับ "ดำน้ำ" และเพิ่ม "เข้าป่า" (คลินิกขอ 2569-10-07: การดูแลต่างกัน)
 *
 * tmc_area_altitude เดิมคือ "ที่สูง/ดำน้ำ" รวมกัน — ข้อมูลจริงยังไม่มีใครติ๊กเลย (0 จาก 101 visit)
 * จึงใช้คอลัมน์เดิมเป็น "ที่สูง" อย่างเดียวได้โดยไม่ต้องย้ายข้อมูล
 *
 * tmc_travel_style = ลักษณะการเดินทาง (เดินทางเอง/backpack, คณะทัวร์ ฯลฯ) ข้อความอิสระ เลือกจากรายการหรือพิมพ์เอง
 */
class AddTmcAreaForestDivingToVisits extends Migration
{
    public function up()
    {
        Schema::table('visits', function (Blueprint $table) {
            if (! Schema::hasColumn('visits', 'tmc_area_diving')) {
                $table->boolean('tmc_area_diving')->default(0)->after('tmc_area_altitude');
            }
            if (! Schema::hasColumn('visits', 'tmc_travel_style')) {
                $table->string('tmc_travel_style', 150)->nullable()->after('tmc_trip_purpose');
            }
            if (! Schema::hasColumn('visits', 'tmc_area_forest')) {
                $table->boolean('tmc_area_forest')->default(0)->after('tmc_area_altitude');
            }
        });
    }

    public function down()
    {
        Schema::table('visits', function (Blueprint $table) {
            foreach (['tmc_area_diving', 'tmc_area_forest', 'tmc_travel_style'] as $c) {
                if (Schema::hasColumn('visits', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
}
