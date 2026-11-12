<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ส่วน "สำหรับสุภาพสตรี" ในซักประวัติ TMC — 2569-10-07
 *
 * - tmc_lmp_end: วันสุดท้ายของประจำเดือนรอบล่าสุด (LMP เดิมยังเป็น "วันแรก")
 *   เก็บวันสิ้นสุดแทนจำนวนวัน เพราะใบซักประวัติเขียนเป็นช่วง 16/09–20/09 · จำนวนวันคำนวณให้บนจอ
 *   ไม่ใช้ visits.lmp_days เพราะคอลัมน์นั้นของ STI = "นับจาก LMP ถึงวันมาตรวจ" คนละความหมาย
 * - tmc_contraception / tmc_contraception_other: ประวัติการคุมกำเนิด (ตัวเลือก + รายละเอียด)
 *   ไม่ใช้ contraceptive_method ของ STI เพราะชุดตัวเลือกคนละชุด ข้อมูลเก่าจะปนกัน
 * - tmc_last_sex_date: มีเพศสัมพันธ์ครั้งสุดท้าย
 *
 * วันที่เก็บเป็นข้อความ วว/ดด/ปปปป (พ.ศ.) แบบเดียวกับคอลัมน์ LMP / tmc_depart_date ที่มีอยู่
 */
class AddTmcFemaleFieldsToVisits extends Migration
{
    public function up()
    {
        Schema::table('visits', function (Blueprint $table) {
            if (! Schema::hasColumn('visits', 'tmc_lmp_end')) {
                $table->string('tmc_lmp_end', 10)->nullable()->after('LMP');
            }
            if (! Schema::hasColumn('visits', 'tmc_contraception')) {
                $table->string('tmc_contraception', 50)->nullable();
            }
            if (! Schema::hasColumn('visits', 'tmc_contraception_other')) {
                $table->string('tmc_contraception_other', 255)->nullable();
            }
            if (! Schema::hasColumn('visits', 'tmc_last_sex_date')) {
                $table->string('tmc_last_sex_date', 10)->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('visits', function (Blueprint $table) {
            foreach (['tmc_lmp_end', 'tmc_contraception', 'tmc_contraception_other', 'tmc_last_sex_date'] as $c) {
                if (Schema::hasColumn('visits', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
}
