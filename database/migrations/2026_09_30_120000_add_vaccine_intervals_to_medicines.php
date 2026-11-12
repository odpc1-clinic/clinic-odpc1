<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ระยะห่างวัคซีนแต่ละเข็ม — ใช้นัดเข็มถัดไปอัตโนมัติ (คลินิก TMC ขอ 2569-09-30)
 *
 * รูปแบบ "0, 1m, 6m" นับจากเข็มแรก (d วัน · w สัปดาห์ · m เดือน · y ปี) · "0" = เข็มเดียว
 * ค่าตั้งต้นตามตารางผู้ใหญ่มาตรฐานที่หมอดูแล้วตอบ OK · 3 ตัวที่ยังไม่ได้ยืนยัน (พิษสุนัขบ้า, JE, dT)
 * มีหมายเหตุไว้ให้หมอแก้ที่หน้า /vaccine-schedule
 */
class AddVaccineIntervalsToMedicines extends Migration
{
    public function up()
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->string('vaccine_intervals', 100)->nullable()->after('is_vaccine');
            $table->string('vaccine_schedule_note', 255)->nullable()->after('vaccine_intervals');
        });

        $defaults = [
            56 => ['0, 1m, 6m', null], 59 => ['0, 1m, 6m', null], 60 => ['0, 1m, 6m', null],   // Hepatitis B
            57 => ['0, 6m', null],                                                            // Hepatitis A
            61 => ['0, 1m, 6m', null],                                                        // Twinrix
            80 => ['0, 2m, 6m', null], 81 => ['0, 2m, 6m', null], 85 => ['0, 2m, 6m', null],  // HPV
            82 => ['0, 28d', null],                                                           // MMR
            74 => ['0, 3m', null],                                                            // Qdenga
            77 => ['0, 2m', null],                                                            // RZV
            71 => ['0, 1m', null],                                                            // Bexsero
            83 => ['0, 14d', null],                                                           // Cholera
            70 => ['0', null], 73 => ['0', null], 75 => ['0', null], 78 => ['0', null], 84 => ['0', null],
            79 => ['0, 7d, 21d', 'ยังไม่ยืนยัน — ตั้งเป็น PrEP 0·7·21 (ถ้า PEP ใช้ 0·3·7·14)'],
            72 => ['0, 28d',     'ยังไม่ยืนยัน — ขึ้นกับยี่ห้อ บางยี่ห้อเข็มเดียว'],
            76 => ['0',          'ยังไม่ยืนยัน — ตั้งเป็นกระตุ้นเข็มเดียว (คนไม่เคยฉีดใช้ 0, 1m, 6m)'],
        ];
        foreach ($defaults as $id => [$spec, $note]) {
            DB::table('medicines')->where('id', $id)->where('is_vaccine', 1)
                ->update(['vaccine_intervals' => $spec, 'vaccine_schedule_note' => $note]);
        }
    }

    public function down()
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->dropColumn(['vaccine_intervals', 'vaccine_schedule_note']);
        });
    }
}
