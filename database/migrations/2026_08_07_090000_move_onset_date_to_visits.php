<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ย้าย "วันที่เริ่มป่วย" จากระดับวินิจฉัย (diagnoses) มาเป็นระดับ visit เดียว
 *
 * เดิมให้กรอกแยกทีละวินิจฉัยในหน้าเพิ่มวินิจฉัย ผู้ใช้เห็นว่ามากเกินจำเป็น —
 * หนึ่ง visit มีวันเริ่มป่วยเดียวพอ ย้ายไปอยู่หน้าตรวจตรงประวัติการเจ็บป่วยปัจจุบันแทน
 * (ผู้ใช้ตัดสินใจ 2569-08-07)
 */
class MoveOnsetDateToVisits extends Migration
{
    public function up()
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->string('onset_date', 10)->nullable()->after('symptom');
        });

        // กู้ข้อมูลที่เคยกรอกไว้ (ถ้ามี) มาใส่ visit — ใช้ค่าแรกที่เจอต่อ visit
        DB::statement('
            UPDATE visits v
            JOIN (
                SELECT visit_id, MIN(onset_date) AS onset_date
                FROM diagnoses
                WHERE onset_date IS NOT NULL
                GROUP BY visit_id
            ) d ON d.visit_id = v.id
            SET v.onset_date = d.onset_date
        ');

        Schema::table('diagnoses', function (Blueprint $table) {
            $table->dropColumn('onset_date');
        });
    }

    public function down()
    {
        Schema::table('diagnoses', function (Blueprint $table) {
            $table->string('onset_date', 10)->nullable()->after('disease_state_other');
        });

        DB::statement('
            UPDATE diagnoses d
            JOIN visits v ON v.id = d.visit_id
            SET d.onset_date = v.onset_date
        ');

        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn('onset_date');
        });
    }
}
