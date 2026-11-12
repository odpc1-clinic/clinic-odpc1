<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ประวัติการตรวจ Syphilis (พยาบาลพิมพ์อิสระ) — ดึงไปแสดงในทะเบียนคู่สัมผัส
 *
 * ไม่ใช้ visits.hiv_sti_test เดิมซ้ำ เพราะคอลัมน์นั้นเป็น tinyint (dropdown 1-5)
 * เก็บข้อความไม่ได้ และมีข้อมูลเก่า 11,034 แถวที่ไม่ควรถูกทับ
 */
class AddSyphilisHistoryToVisits extends Migration
{
    public function up()
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->text('syphilis_history')->nullable()->after('hiv_sti_test');
        });
    }

    public function down()
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn('syphilis_history');
        });
    }
}
