<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ไฟล์แนบแผนการเดินทาง TMC (กำหนดการ/ตั๋ว/จดหมายเชิญ/วีซ่า) — 2569-10-07
 *
 * แบบเดียวกับ tb_lab_files: ตัวไฟล์ไม่อยู่ในฐานข้อมูล เก็บเข้ารหัส (Crypt) ที่
 * storage/app/private/tmc_travel_files/{stored_name} — เอกสารเดินทางมีเลขพาสปอร์ต/ชื่อ-ที่อยู่
 * ห้ามวางใน public/ ต้องเปิดผ่าน route ที่ล็อกอินแล้วเท่านั้น · stored_name สุ่ม ไม่มี HN ในชื่อ
 *
 * ผูกกับ visit (แผนเดินทางครั้งนั้น) + patient_id ไว้ดูย้อนหลังข้าม visit ได้ (ฉีดเข็มต่อไม่ต้องขอเอกสารใหม่)
 * ไม่ทำ soft delete / ไม่ใส่ foreign key ตามธรรมเนียมตารางเดิม — ประวัติการลบอยู่ใน activity log (Auditable)
 */
class CreateTmcTravelFilesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('tmc_travel_files')) {
            return;
        }
        Schema::create('tmc_travel_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('visit_id')->index();
            $table->unsignedBigInteger('patient_id')->nullable()->index();
            $table->string('title', 150)->nullable();          // เช่น "ตั๋วเครื่องบิน", "จดหมายเชิญ"
            $table->string('original_name', 255)->nullable();
            $table->string('mime', 100);
            $table->unsignedInteger('size');                   // ขนาดไฟล์จริงก่อนเข้ารหัส (byte)
            $table->string('stored_name', 100)->unique();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('tmc_travel_files');
    }
}
