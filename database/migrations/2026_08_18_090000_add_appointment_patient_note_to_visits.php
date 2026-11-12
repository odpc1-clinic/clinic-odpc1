<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * แยก "ข้อความถึงคนไข้" ออกจาก "เหตุผลการนัด"
 *
 * appointment_reason เป็นช่องที่เจ้าหน้าที่ใช้คุยกันเอง/ส่งเวรกันมาตลอด
 * แต่ระบบดันเอาไปพิมพ์ลงใบนัดที่คนไข้ถือกลับบ้าน และส่งเข้า LINE คนไข้ด้วย
 * ซึ่งเสี่ยงมากกับคลินิกโรคติดต่อทางเพศสัมพันธ์ เพราะข้อความอาจเด้งขึ้นหน้าจอ
 * ให้คนรอบตัวคนไข้เห็น
 *
 * ตั้งใจไม่ย้ายข้อมูลเก่ามาใส่ช่องใหม่ ถึงจะทำได้ก็ไม่ควร — ของเดิมเขียนโดยไม่รู้ว่า
 * คนไข้จะได้อ่าน การก็อปมาทั้งก้อนเท่ากับเผยแพร่ของเก่าต่อ ปล่อยว่างแล้วให้พยาบาล
 * เขียนใหม่เฉพาะใบที่อยากบอกคนไข้จริงๆ ปลอดภัยกว่า
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->string('appointment_patient_note', 255)
                  ->nullable()
                  ->after('appointment_reason');
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn('appointment_patient_note');
        });
    }
};
