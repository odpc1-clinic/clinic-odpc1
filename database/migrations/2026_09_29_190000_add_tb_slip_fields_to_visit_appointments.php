<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ข้อมูลที่ใบนัดคลินิกวัณโรคต้องใช้ (2569-09-29)
 *
 * ใบนัดวัณโรคเป็นคนละแบบกับใบนัด STI — ต้องมีเวลานัด · แพทย์ที่ทำการนัด · ชื่อผู้นัด
 * และ "ที่ต้องดำเนินการก่อนพบแพทย์" (เช่น CXR, AFB x2) ตามฟอร์มเดิมของคลินิก
 * คลินิกต้องการกรอกในระบบก่อน แล้วใบนัดดึงไปพิมพ์ ไม่ใช่มาพิมพ์เติมบนใบ
 *
 * เก็บที่ระดับ "นัดแต่ละวัน" ไม่ใช่ที่ visit เพราะหนึ่ง visit นัดได้หลายวัน
 * และแต่ละวันอาจต้องทำก่อนพบแพทย์ไม่เหมือนกัน
 *
 * ว่างได้ทั้งหมด — คลินิกอื่นไม่ใช้ช่องพวกนี้ และนัดเก่าก็ไม่มีข้อมูล
 */
class AddTbSlipFieldsToVisitAppointments extends Migration
{
    public function up()
    {
        Schema::table('visit_appointments', function (Blueprint $table) {
            $table->string('appt_time', 5)->nullable()->after('appointment_date');      // "08:30"
            $table->string('pre_visit', 500)->nullable()->after('staff_note');         // ที่ต้องทำก่อนพบแพทย์
            $table->unsignedBigInteger('doctor_id')->nullable()->after('pre_visit');   // แพทย์ที่ทำการนัด
            $table->unsignedBigInteger('made_by')->nullable()->after('doctor_id');     // ผู้นัด (คนที่ลงนัดในระบบ)
        });
    }

    public function down()
    {
        Schema::table('visit_appointments', function (Blueprint $table) {
            $table->dropColumn(['appt_time', 'pre_visit', 'doctor_id', 'made_by']);
        });
    }
}
