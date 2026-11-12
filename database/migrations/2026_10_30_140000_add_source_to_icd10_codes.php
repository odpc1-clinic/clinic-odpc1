<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * แยกรหัสที่หมอคัดไว้ (clinic) ออกจาก ICD-10-TM ชุดเต็ม (tm) — พยาบาลขอใส่ ICD-10 ไทยทั้งหมด 2569-10-30
 *
 * ชุดเต็มมี ~15,700 รหัส ส่งไปกับหน้าเว็บทุกหน้าไม่ได้ (หน้าตรวจจะช้า)
 * ตัวเลือกจึงโหลดเฉพาะ source=clinic ไว้ก่อน แล้วค้นชุดเต็มจากเซิร์ฟเวอร์ตอนพิมพ์
 * รหัสเดิมทุกแถวเป็นของหมอ -> ค่าตั้งต้น clinic
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('icd10_codes', function (Blueprint $t) {
            $t->string('source', 16)->default('clinic')->after('code');
            $t->index(['source', 'active']);
        });
    }

    public function down(): void
    {
        Schema::table('icd10_codes', function (Blueprint $t) {
            $t->dropIndex(['source', 'active']);
            $t->dropColumn('source');
        });
    }
};
