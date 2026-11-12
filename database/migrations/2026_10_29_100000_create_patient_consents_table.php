<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ความยินยอม PDPA ของคนไข้ (2569-10-29)
 * เก็บเป็นประวัติ — บันทึกครั้งใหม่ = แถวใหม่ ไม่เขียนทับ (ต้องพิสูจน์ได้ว่าเคยยินยอมอะไร เมื่อไหร่ ใครรับเรื่อง)
 * ค่าปัจจุบัน = แถวล่าสุดของคนไข้ · ช่องยินยอม: 1 = ยินยอม, 0 = ไม่ยินยอม, null = ไม่ได้ถาม
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_consents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id')->index();
            $table->string('notice_version', 20)->nullable();      // ฉบับประกาศความเป็นส่วนตัวที่แจ้ง
            $table->boolean('notice_informed')->default(false);    // แจ้งประกาศแล้ว
            $table->boolean('line')->nullable();                   // ส่ง LINE แจ้งนัด/เตือนนัด
            $table->boolean('photo')->nullable();                  // เก็บรูปจากบัตรประชาชน
            $table->boolean('contact')->nullable();                // ติดต่อคู่สัมผัส
            $table->boolean('research')->nullable();               // เซ็นฟอร์มวิจัย (ฟอร์มแยกของโครงการ)
            $table->string('research_project', 255)->nullable();
            $table->string('method', 20)->default('verbal');       // verbal = เจ้าหน้าที่สัมภาษณ์
            $table->string('signer', 20)->default('self');         // self / guardian / school
            $table->string('signer_name', 255)->nullable();        // ชื่อผู้ปกครอง/โรงเรียน
            $table->string('attachment', 255)->nullable();         // ไฟล์แนบเข้ารหัส เช่น หนังสือจากโรงเรียน
            $table->text('note')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();     // เจ้าหน้าที่ที่รับเรื่อง
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_consents');
    }
};
