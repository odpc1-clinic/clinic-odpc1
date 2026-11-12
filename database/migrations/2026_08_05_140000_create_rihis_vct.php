<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ทะเบียน RIHIS-VCT (แบบบันทึกการให้บริการปรึกษาและตรวจเอชไอวี)
 *
 * รอบแรกให้พยาบาลเลือกลงเอง ยังไม่ดึงจาก visit อัตโนมัติ (ผู้ใช้เลือกเอง 2569-08-05)
 * แต่เก็บ visit_id/patient_id ไว้ด้วย เผื่อต่อยอดให้เติมอัตโนมัติทีหลัง
 *
 * ชื่อคอลัมน์อิงตามคอลัมน์ในฟอร์มกระดาษ RIHIS-VCT เพื่อให้ไล่กับไฟล์ Excel ได้ง่าย
 */
class CreateRihisVct extends Migration
{
    public function up()
    {
        Schema::create('rihis_vct', function (Blueprint $table) {
            $table->id();

            // ผูกกลับระบบ (ไม่บังคับ) — ใส่ไว้ให้ต่อยอดดึงข้อมูลอัตโนมัติภายหลัง
            $table->unsignedBigInteger('visit_id')->nullable();
            $table->unsignedBigInteger('patient_id')->nullable();

            $table->string('service_date', 10)->nullable();   // วันที่รับบริการ พ.ศ. Y-m-d (A,B,C)
            $table->string('name', 100)->nullable();          // E — ใช้ชื่อเต็มได้ (ผู้ใช้ยืนยัน)
            $table->string('surname', 100)->nullable();       // F
            $table->string('uic', 40)->nullable();            // G
            $table->string('hn', 30)->nullable();             // H
            $table->string('id_card', 20)->nullable();        // I
            $table->unsignedSmallInteger('age')->nullable();  // J
            $table->string('birth_date', 10)->nullable();     // AY (พ.ศ. Y-m-d)

            $table->unsignedTinyInteger('sex')->nullable();          // K 1ช 2ญ
            $table->unsignedTinyInteger('marital')->nullable();      // M 1-5
            $table->unsignedTinyInteger('nationality')->nullable();  // N 1ไทย..5อื่นๆ
            $table->unsignedTinyInteger('target_group')->nullable(); // O 1-8
            $table->unsignedTinyInteger('msm_sub')->nullable();      // P 1MSM 2TG 3MSW 4TGSW
            $table->unsignedTinyInteger('sw_mode')->nullable();      // Q 1Venue 2NonVenue
            $table->unsignedTinyInteger('occ_thai')->nullable();     // R
            $table->unsignedTinyInteger('occ_migrant')->nullable();  // S

            $table->unsignedTinyInteger('referral')->nullable();     // T 0ไม่มี 1มี
            $table->unsignedTinyInteger('past_service')->nullable(); // U 0/1/2

            // บริการที่ได้รับ — ส่วนใหญ่เป็น 1 ทั้งหมด จึงตั้ง default 1
            $table->unsignedTinyInteger('pre_test')->default(1);     // V
            $table->unsignedTinyInteger('hiv_test')->default(1);     // W
            $table->unsignedTinyInteger('post_test')->nullable();    // X 0ยังไม่ฟังผล 1ฟังแล้ว

            $table->string('result_date', 10)->nullable();           // Y,Z,AA (พ.ศ.)
            $table->unsignedTinyInteger('blood_result')->nullable(); // AC 1ลบ 2บวก 3ไม่แน่ชัด

            $table->string('referral_unit', 150)->nullable();        // AD
            $table->unsignedTinyInteger('referral_type')->nullable();// AE
            $table->unsignedTinyInteger('referrer')->nullable();     // AF
            $table->string('referrer_detail', 150)->nullable();      // AG

            $table->unsignedTinyInteger('cd4_sent')->nullable();     // AH
            $table->string('cd4', 20)->nullable();                   // AI
            $table->unsignedTinyInteger('repeat_appt')->nullable();  // AJ
            $table->string('repeat_date', 10)->nullable();           // AK (พ.ศ.)
            $table->unsignedTinyInteger('refer_other')->nullable();  // AL

            $table->unsignedTinyInteger('test_method')->nullable();  // AM 1SDR 2กลับมาฟังผล
            $table->string('followup', 30)->nullable();              // AN
            $table->string('hospital1', 150)->nullable();            // AQ
            $table->string('note', 255)->nullable();                 // AR
            $table->string('form5', 100)->nullable();                // AS

            $table->timestamps();

            $table->index('service_date');
            $table->index('visit_id');
            $table->index('hn');
        });
    }

    public function down()
    {
        Schema::dropIfExists('rihis_vct');
    }
}
