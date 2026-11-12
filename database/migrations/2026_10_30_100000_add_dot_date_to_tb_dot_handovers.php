<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * เรื่องส่งเวร DOT จำวันที่ของหน้าที่พยาบาลเปิดอยู่ตอนกดส่งเวร (คลินิกแจ้ง 2569-10-30)
 * เดิมรู้แค่เวลาที่กด (created_at) — เปิดย้อนดูวันที่ 29 แล้วส่งเวร ขึ้นเป็นวันที่ 30
 * เก็บเป็น พ.ศ. แบบเดียวกับ tb_dot_days.dot_date · แถวเก่าไม่มีค่า = ใช้วันที่กดแทน
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_dot_handovers', function (Blueprint $t) {
            $t->date('dot_date')->nullable()->after('tb_registration_id');
        });
    }

    public function down(): void
    {
        Schema::table('tb_dot_handovers', function (Blueprint $t) {
            $t->dropColumn('dot_date');
        });
    }
};
