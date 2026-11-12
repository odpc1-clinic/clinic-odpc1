<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * จุด/เส้นที่พยาบาลมาร์คบนรูปปอด ในส่วนการตรวจร่างกายของใบคัดกรองวัณโรค
 *
 * เก็บเป็น JSON แบบเดียวกับ visits.body_check_marks ของการ์ด STI
 * (คลินิกขอ 2569-09-29 — ใช้รูปโครงปอดพร้อมเส้นแบ่งกลีบ ไม่มีสีพื้น)
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('tb_screenings', function (Blueprint $table) {
            $table->json('lung_marks')->nullable()->after('pe_neuro');
        });
    }

    public function down()
    {
        Schema::table('tb_screenings', function (Blueprint $table) {
            $table->dropColumn('lung_marks');
        });
    }
};
