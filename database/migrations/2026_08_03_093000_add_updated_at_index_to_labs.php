<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index ให้แถบแจ้งเตือน "ผลแล็บออกแล้ว" (ดู NotificationController)
 *
 * ใช้ labs.updated_at เป็นตัวจับเวลาที่ผลถูกอนุมัติ เพราะ approve_date เก็บเป็น พ.ศ.
 * และเวลาแยกอยู่คนละคอลัมน์ (approve_at) จึงเทียบ "ใหม่กว่า X" ตรงๆ ไม่ได้
 */
class AddUpdatedAtIndexToLabs extends Migration
{
    public function up()
    {
        Schema::table('labs', function (Blueprint $table) {
            $table->index('updated_at', 'labs_updated_at_idx');
        });
    }

    public function down()
    {
        Schema::table('labs', function (Blueprint $table) {
            $table->dropIndex('labs_updated_at_idx');
        });
    }
}
