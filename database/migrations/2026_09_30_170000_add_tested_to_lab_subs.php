<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ผู้ทดสอบ / วันที่ทดสอบ รายรายการตรวจ (ห้อง Lab ขอ 2569-09-30)
 * แต่ละ Method คนทำไม่เหมือนกัน และออกผลไม่พร้อมกัน — เก็บที่ระดับใบ Lab ไม่พอ
 * labs.tested_by / tested_date (เพิ่มเมื่อเช้า) ยังคงไว้ ไม่มีที่ใช้แล้ว แต่ไม่ลบเผื่อข้อมูลที่ลงไปแล้ว
 */
class AddTestedToLabSubs extends Migration
{
    public function up()
    {
        Schema::table('lab_subs', function (Blueprint $table) {
            $table->unsignedBigInteger('tested_by')->nullable()->after('result');
            $table->date('tested_date')->nullable()->after('tested_by');
        });
    }

    public function down()
    {
        Schema::table('lab_subs', function (Blueprint $table) {
            $table->dropColumn(['tested_by', 'tested_date']);
        });
    }
}
