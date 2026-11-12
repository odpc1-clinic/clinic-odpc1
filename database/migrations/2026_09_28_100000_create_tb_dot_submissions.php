<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * หลักฐานการกินยาที่คนไข้ส่งเข้ามา (Video DOT)
 *
 * คนไข้ถ่ายคลิป/รูปตอนกินยาส่งเข้า LINE OA เดิมของคลินิก
 * ไฟล์เก็บแบบเข้ารหัสในเครื่อง (เหมือนรูปผังครอบครัว) ไม่วางไว้ใน public
 *
 * แถวนี้เป็นแค่ "หลักฐานที่ส่งมา" — การลง DOT จริงยังอยู่ที่ tb_dot_days
 * และต้องให้พยาบาลกดยืนยันเสมอ ไม่ให้ AI ติ๊กเอง (คลินิกกำหนด 2569-09-28)
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('tb_dot_submissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tb_registration_id')->nullable()->index();
            $table->unsignedInteger('patient_id')->nullable()->index();

            $table->date('dot_date')->index();          // พ.ศ. — วันที่ถือว่าเป็นของ DOT วันไหน
            $table->timestamp('received_at')->nullable(); // เวลาจริงที่คนไข้ส่ง (ตามเวลา LINE)

            $table->string('line_user_id', 64)->nullable()->index();
            $table->string('line_message_id', 64)->nullable()->unique();  // กันนำเข้าซ้ำตอนดึงจากที่พักข้อมูล
            $table->string('kind', 10)->default('video');                  // video | image
            $table->string('file', 191)->nullable();                       // ชื่อไฟล์ใน private/tb_dot
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->string('source', 20)->default('line');                 // line | manual

            // ผล AI ช่วยนับเม็ดยา — เป็นข้อมูลประกอบ ไม่ใช่คำตัดสิน
            $table->string('ai_status', 20)->nullable();                   // pending | ok | warn | error
            $table->json('ai_result')->nullable();
            $table->string('ai_note', 255)->nullable();

            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['tb_registration_id', 'dot_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('tb_dot_submissions');
    }
};
