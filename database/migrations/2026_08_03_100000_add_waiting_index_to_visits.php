<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index ให้การแจ้งเตือน "คนไข้รอเรียกเข้าห้องตรวจนานเกิน 30 นาที"
 *
 * query นั้นกรองด้วย date + status (วันนี้ + ห้องตรวจ) ซึ่งเดิมไม่มี index เลย
 * ทำให้ full-scan visits ทั้งตาราง (10,000+ แถว) ทุก 10 วินาที ทุกเครื่อง
 * วัดจริงก่อนแก้ 14 ms ต่อครั้ง (จากปกติ 1.3 ms)
 *
 * date นำหน้า status เพราะ date คัดทิ้งได้เกือบหมดในทีเดียว (เหลือแค่ visit ของวันนั้น)
 */
class AddWaitingIndexToVisits extends Migration
{
    public function up()
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->index(['date', 'status'], 'visits_date_status_idx');
        });
    }

    public function down()
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropIndex('visits_date_status_idx');
        });
    }
}
