<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * สมุดเหลือง (International Certificate of Vaccination) — 1 คน/เล่ม เก็บที่ตัวคนไข้
 * yellow_book_no = เลขเล่มปัจจุบัน · yellow_book_history = ประวัติสมุดเดิม (ถ้ามี)
 */
class AddYellowBookToPatients extends Migration
{
    public function up()
    {
        Schema::table('patients', function (Blueprint $table) {
            if (!Schema::hasColumn('patients', 'yellow_book_no')) {
                $table->string('yellow_book_no', 50)->nullable()->after('passport');
            }
            if (!Schema::hasColumn('patients', 'yellow_book_history')) {
                $table->text('yellow_book_history')->nullable()->after('yellow_book_no');
            }
        });
    }

    public function down()
    {
        Schema::table('patients', function (Blueprint $table) {
            foreach (['yellow_book_history', 'yellow_book_no'] as $c) {
                if (Schema::hasColumn('patients', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
}
