<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ข้อมูลแผน DOT ของคนไข้ — ให้หน้าทะเบียน DOT รวมแสดงได้เหมือนตารางที่พยาบาลทำใน Excel
 *
 * ของเดิมในบัตรมีแค่ "DOT หรือไม่" กับ "ใครกำกับ" แต่ตารางที่ใช้งานจริงต้องการ
 * เหตุผลที่ต้อง DOT · ช่องทางที่คนไข้ส่ง · เวลานัดประจำวัน · และสูตรยาแบบละเอียดรายเม็ด
 * (สูตรในบัตรเป็นชื่อย่อ เช่น 2HRZE/4HR แต่ตาราง DOT ต้องเห็น BDQ(100)*3 จ,พ,ศ)
 *
 * ความถี่แยกสองกลุ่มตามที่คลินิกใช้: กินทุกวัน (TB) กับสัปดาห์ละครั้ง (TPT 3HP) — 2569-09-28
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('tb_registrations', function (Blueprint $table) {
            $table->string('dot_reason', 60)->nullable()->after('dot_off_by');        // Active phase · MDR · Pre-XDR · LTBI
            $table->string('dot_channel', 60)->nullable()->after('dot_reason');       // Line OA · WeChat · ศูนย์บริการฯ
            $table->string('dot_time', 60)->nullable()->after('dot_channel');         // 7.30 น. · 18-19 น.
            $table->unsignedTinyInteger('dot_frequency')->default(1)->after('dot_time'); // 1 = ทุกวัน · 2 = สัปดาห์ละครั้ง
            $table->unsignedTinyInteger('dot_weekday')->nullable()->after('dot_frequency'); // 1=จันทร์ ... 7=อาทิตย์
            $table->text('dot_regimen_text')->nullable()->after('dot_weekday');       // สูตรยาแบบที่เขียนในตาราง DOT
        });
    }

    public function down()
    {
        Schema::table('tb_registrations', function (Blueprint $table) {
            $table->dropColumn([
                'dot_reason', 'dot_channel', 'dot_time',
                'dot_frequency', 'dot_weekday', 'dot_regimen_text',
            ]);
        });
    }
};
