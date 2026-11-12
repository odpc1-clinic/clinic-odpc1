<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * รหัสผูก LINE ที่คนไข้กรอกในฟอร์มใน LINE (LIFF) — 2569-10-22
 *
 * ทำไมต้องมีตาราง: server ใหม่อยู่วงใน LINE ยิงเข้ามาไม่ได้ ฟอร์มจึงส่งผ่านที่พัก Apps Script
 * แล้ว server มาดึงทีหลัง (line:pull-links) — ต้องจำว่าดึงอะไรไปแล้ว (relay_id)
 * ไม่งั้นดึงซ้ำตอนเน็ตหลุด รหัสที่ใช้ไปแล้วจะถูกตรวจใหม่แล้วส่ง "รหัสผิด" หาคนไข้ที่เพิ่งผูกสำเร็จ
 *
 * ผูกได้เฉพาะรหัส 6 หลักจากเจ้าหน้าที่เท่านั้น — ไม่รับคำขอแบบกรอกชื่อ/เบอร์ (ตัดสินใจ 2569-10-22)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('line_link_requests')) {
            return;
        }

        Schema::create('line_link_requests', function (Blueprint $table) {
            $table->id();
            $table->string('relay_id', 64)->unique();        // รหัสแถวฝั่ง Apps Script
            $table->string('line_user_id', 64)->index();
            $table->string('line_display_name')->nullable();
            $table->string('code', 10)->nullable();
            $table->string('status', 10);                     // linked · failed
            $table->unsignedBigInteger('patient_id')->nullable()->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('line_link_requests');
    }
};
