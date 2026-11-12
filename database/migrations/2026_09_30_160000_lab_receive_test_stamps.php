<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flow ห้อง Lab ใหม่ (คลินิกกำหนด 2569-09-30) — ISO 15189 ต้องรู้ว่าใครทำอะไรเมื่อไหร่
 *   1. พยาบาลส่ง Lab (collected_date/at มีอยู่แล้ว)
 *   2. ห้อง Lab กด "รับ Lab"   -> received_by / received_at (คนที่ login + เวลาจริง)
 *   3. ผู้ทดสอบ (Test by)     -> tested_by / tested_date (เลือกได้ วันที่ตั้งต้นเป็นวันนี้)
 *   4. กด "Report" / "Approve" -> report_by/date/at และ approve_by/date/at (คนที่ login + เวลาจริง เลือกชื่อเองไม่ได้แล้ว)
 */
class LabReceiveTestStamps extends Migration
{
    public function up()
    {
        Schema::table('labs', function (Blueprint $table) {
            $table->unsignedBigInteger('received_by')->nullable()->after('collected_at');
            $table->dateTime('received_at')->nullable()->after('received_by');
            $table->unsignedBigInteger('tested_by')->nullable()->after('received_at');
            $table->date('tested_date')->nullable()->after('tested_by');
        });
    }

    public function down()
    {
        Schema::table('labs', function (Blueprint $table) {
            $table->dropColumn(['received_by', 'received_at', 'tested_by', 'tested_date']);
        });
    }
}
