<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ตารางติดตามการรักษาวัณโรค (บัตร TB 01 แท็บ "ติดตาม") — 2569-10-05
 *
 * tb_monitor_checks     ติ๊กมือในตารางติดตาม สำหรับรายการที่ไม่มีที่เก็บผลในระบบ
 *                       (Inform consent, EKG/QTcF, CD4 จาก รพ.อื่น, ช่องขยายระยะเข้มข้น)
 *                       หรือรายการที่ไปตรวจที่อื่นแล้วไม่ได้ลงในระบบ
 *                       หนึ่งช่องในตาราง = หนึ่งแถว (unique บัตร+รายการ+จุดเวลา) กดซ้ำ = แก้แถวเดิม
 * tb_vision_tests       ตรวจสายตา (VA + ตาบอดสี) — คลินิกตรวจทุกรายก่อนเริ่มยา และทุกเดือนถ้าได้ E / Linezolid
 * tb_depression_screens คัดกรองซึมเศร้า 2Q / 9Q / 8Q ของกรมสุขภาพจิต — เก็บคำตอบรายข้อเป็น JSON
 *                       (แบบประเมินอาจปรับข้อความ/ข้อในอนาคต ไม่อยากเพิ่มคอลัมน์ทีละข้อ) + คะแนนรวมไว้ค้น/รายงาน
 *
 * ไม่ใส่ foreign key: ตารางเดิมของระบบไม่ใช้ FK และ visits.id เป็น int ส่วน tb_registrations.id เป็น bigint
 * วันที่เก็บเป็น พ.ศ. 'Y-m-d' (string 10) เหมือนช่องวันที่อื่นของบัตร TB
 */
class CreateTbMonitorTables extends Migration
{
    public function up()
    {
        Schema::create('tb_monitor_checks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tb_registration_id');
            $table->string('check_key', 30);      // ตรงกับ key ใน App\Support\TbMonitorDefinitions
            $table->string('point_key', 10);      // d0 / w4 / m2 / p6 ...
            $table->string('done_date', 10)->nullable();
            $table->string('note', 255)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->unique(['tb_registration_id', 'check_key', 'point_key'], 'tb_monitor_checks_unique');
        });

        Schema::create('tb_vision_tests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tb_registration_id')->index();
            $table->unsignedInteger('visit_id')->nullable()->index();
            $table->string('test_date', 10);
            $table->string('va_right', 20)->nullable();   // 20/20 · 6/6 · CF · HM · PL
            $table->string('va_left', 20)->nullable();
            $table->enum('color_result', ['normal', 'abnormal'])->nullable();
            $table->unsignedTinyInteger('color_plates_correct')->nullable();   // Ishihara อ่านถูกกี่แผ่น
            $table->unsignedTinyInteger('color_plates_total')->nullable();
            $table->string('note', 255)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('tb_depression_screens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tb_registration_id')->index();
            $table->unsignedInteger('visit_id')->nullable()->index();
            $table->string('screen_date', 10);
            $table->json('q2')->nullable();                 // {"1": 0|1, "2": 0|1}
            $table->boolean('q2_positive')->default(false);
            $table->json('q9')->nullable();                 // {"1": 0-3, ... "9": 0-3}
            $table->unsignedTinyInteger('q9_score')->nullable();
            $table->json('q8')->nullable();                 // {"1": 0|1, ... "3a": 0|1 ...}
            $table->unsignedTinyInteger('q8_score')->nullable();
            $table->string('interpretation', 255)->nullable();
            $table->boolean('referred')->default(false);
            $table->string('note', 255)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('tb_depression_screens');
        Schema::dropIfExists('tb_vision_tests');
        Schema::dropIfExists('tb_monitor_checks');
    }
}
