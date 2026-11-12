<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index สำหรับแถบแจ้งเตือนความเคลื่อนไหว (ดู NotificationController)
 *
 * ทุกเครื่องที่เปิดระบบจะ poll ถามว่ามี visit ใหม่/ย้ายห้องใหม่ทุก 10 วินาที
 * ถ้าไม่มี index จะ full-scan ตาราง visits (หมื่นกว่าแถว) ทุกครั้ง คูณจำนวนเครื่อง
 */
class AddActivityIndexToVisits extends Migration
{
    public function up()
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->index('room_entered_at', 'visits_room_entered_at_idx');
            $table->index('created_at', 'visits_created_at_idx');
        });
    }

    public function down()
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropIndex('visits_room_entered_at_idx');
            $table->dropIndex('visits_created_at_idx');
        });
    }
}
