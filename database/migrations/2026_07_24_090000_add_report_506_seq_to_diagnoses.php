<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * เก็บเลข E0 (ลำดับที่ให้ไปแล้ว) + ปี พ.ศ. ต่อเคส 506
 * เพื่อให้ E0 นับ 1 ใหม่ทุกปี และ export รอบถัดไปในปีเดียวกันนับต่อได้ (กันเลขซ้ำ/ข้าม)
 */
class AddReport506SeqToDiagnoses extends Migration
{
    public function up()
    {
        Schema::table('diagnoses', function (Blueprint $table) {
            if (!Schema::hasColumn('diagnoses', 'report_506_year')) {
                $table->smallInteger('report_506_year')->nullable()->after('report_506_sent_at'); // พ.ศ.
            }
            if (!Schema::hasColumn('diagnoses', 'report_506_seq')) {
                $table->integer('report_506_seq')->nullable()->after('report_506_year'); // E0 ของปีนั้น
            }
        });
    }

    public function down()
    {
        Schema::table('diagnoses', function (Blueprint $table) {
            foreach (['report_506_seq', 'report_506_year'] as $c) {
                if (Schema::hasColumn('diagnoses', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
}
