<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ตารางเสริมสำหรับสมุดทะเบียนติดตามผู้ป่วย 5 โรคหลัก + คู่สัมผัส (ContactSTI)
 * เก็บเฉพาะช่องที่พยาบาลกรอกเอง ซึ่งไม่มีในตาราง visits/patients
 * (การติดตามคู่สัมผัส + ผล HIV ใช้ของ visit เดิม ไม่ซ้ำที่นี่)
 */
class CreateContactSti extends Migration
{
    public function up()
    {
        Schema::create('contact_sti', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('visit_id')->unique();
            $table->string('patient_type', 40)->nullable();   // ประเภท (MSM/FSW/...)
            $table->string('fu_status', 60)->nullable();       // สถานะ F/U
            $table->string('sex_date', 60)->nullable();        // วันที่มีเพศสัมพันธ์ ใน 3 ด (ข้อความอิสระ)
            $table->string('partner_type', 60)->nullable();    // คู่สัมผัสใน 3 เดือน (ชนิด)
            $table->string('note', 255)->nullable();           // หมายเหตุ
            $table->timestamps();

            $table->index('visit_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('contact_sti');
    }
}
