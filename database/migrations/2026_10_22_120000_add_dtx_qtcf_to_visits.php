<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DTX (เจาะน้ำตาลปลายนิ้วที่คลินิก) + QTcF จาก EKG ที่ทำที่คลินิกเอง — คลินิกวัณโรค (2569-10-22)
 *
 * ทำไม: เดิมไม่มีที่ลงทั้งคู่ — DTX ไม่ใช่แล็บ N Health (FBS ในตารางติดตามดึงจากแล็บนอกอย่างเดียว)
 * EKG ติ๊กมือในตารางติดตามได้แต่ไม่มีตัวเลข · เก็บที่ visit เพราะวัดพร้อมสัญญาณชีพในวันที่มาตรวจ
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            if (! Schema::hasColumn('visits', 'dtx')) {
                $table->unsignedSmallInteger('dtx')->nullable()->after('spo2');    // mg/dL
            }
            if (! Schema::hasColumn('visits', 'qtcf')) {
                $table->unsignedSmallInteger('qtcf')->nullable()->after('dtx');    // ms
            }
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            foreach (['qtcf', 'dtx'] as $c) {
                if (Schema::hasColumn('visits', $c)) { $table->dropColumn($c); }
            }
        });
    }
};
