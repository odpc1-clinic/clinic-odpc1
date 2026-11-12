<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ประเภทการมาของใบคัดกรอง — คัดกรองครั้งแรก/walk-in หรือมาติดตาม (F/U)
 * ใช้ตัดสินว่าจะแสดงตารางให้คะแนนอาการไหม (F/U ไม่ต้องให้คะแนนซ้ำ) — คลินิกขอ 2569-09-29
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('tb_screenings', function (Blueprint $table) {
            $table->unsignedTinyInteger('visit_type')->nullable()->after('chief_complaint');
        });
    }

    public function down()
    {
        Schema::table('tb_screenings', function (Blueprint $table) {
            $table->dropColumn('visit_type');
        });
    }
};
