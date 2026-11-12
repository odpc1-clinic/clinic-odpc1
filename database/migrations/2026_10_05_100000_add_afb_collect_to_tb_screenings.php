<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ผล AFB Collect 1 / Collect 2 ของใบส่งตรวจ (2569-10-05)
 *
 * ที่มา: ส่วนส่งตรวจมีผล AFB ช่องเดียว แต่ตารางเสมหะในบัตร TB 01 แยก Spot / Collect 1 / Collect 2
 * คนไข้ที่มีบัตรแล้ว ผลลงตารางเสมหะโดยตรง (ไม่ต้องใช้คอลัมน์นี้) — คอลัมน์นี้สำหรับคนไข้คัดกรองที่ยังไม่มีบัตร
 * Spot ใช้คอลัมน์เดิม lab_afb_result (ข้อมูลเก่าเป็น Spot อยู่แล้ว ไม่ต้องย้าย)
 * โค้ดรองรับก่อนรัน: ยังไม่มีคอลัมน์ = คนไข้คัดกรองกรอกได้แค่ Spot (TbScreening::hasAfbCollect)
 */
class AddAfbCollectToTbScreenings extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('tb_screenings', 'lab_afb_collect1')) {
            Schema::table('tb_screenings', function (Blueprint $table) {
                $table->string('lab_afb_collect1', 20)->nullable()->after('lab_afb_result');
            });
        }
        if (! Schema::hasColumn('tb_screenings', 'lab_afb_collect2')) {
            Schema::table('tb_screenings', function (Blueprint $table) {
                $table->string('lab_afb_collect2', 20)->nullable()->after('lab_afb_collect1');
            });
        }
    }

    public function down()
    {
        foreach (['lab_afb_collect2', 'lab_afb_collect1'] as $col) {
            if (Schema::hasColumn('tb_screenings', $col)) {
                Schema::table('tb_screenings', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }
    }
}
