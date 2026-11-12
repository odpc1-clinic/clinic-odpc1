<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ไฟล์ผลตรวจจากที่อื่น (สแกน/รูปถ่าย/PDF ผลเลือดจาก รพ.อื่น/แล็บเอกชน) ของบัตร TB 01 — 2569-10-05
 *
 * ตัวไฟล์ไม่อยู่ในฐานข้อมูล: เก็บเข้ารหัส (Crypt) ไว้ที่ storage/app/private/tb_lab_files/{stored_name}
 * เหมือนรูปผังครอบครัว — เป็นข้อมูลสุขภาพ ห้ามวางใน public/ ต้องเปิดผ่าน route ที่ล็อกอินแล้วเท่านั้น
 * stored_name สุ่ม ไม่มี HN/ชื่อคนไข้ในชื่อไฟล์ (หลุดไฟล์ออกไปก็ไม่รู้ว่าของใคร)
 *
 * ไม่ทำ soft delete: ลบแถว = ลบไฟล์ทิ้งด้วย (ประวัติว่าใครลบอะไรอยู่ใน activity log ผ่าน Auditable)
 * ไม่ใส่ foreign key ตามธรรมเนียมตารางเดิมของระบบ · test_date เก็บ พ.ศ. 'Y-m-d' เหมือนช่องวันที่อื่นในบัตร TB
 */
class CreateTbLabFilesTable extends Migration
{
    public function up()
    {
        Schema::create('tb_lab_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tb_registration_id')->index();
            $table->unsignedBigInteger('patient_id')->nullable()->index();
            $table->string('test_date', 10)->nullable();       // วันที่ตรวจตามใบผล (พ.ศ.)
            $table->string('source', 150)->nullable();         // รพ./แล็บที่ตรวจ
            $table->string('title', 150)->nullable();          // รายการ เช่น "CBC, LFT"
            $table->string('note', 500)->nullable();
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
        Schema::dropIfExists('tb_lab_files');
    }
}
