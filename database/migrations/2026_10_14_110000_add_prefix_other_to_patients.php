<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * คำนำหน้าชื่อ "อื่นๆ" พิมพ์เอง (คลินิกขอ 2569-10-14) — ใช้คู่กับ patients.prefix = 99
 * ยศ/ตำแหน่งที่ไม่อยู่ในรายการ เช่น ว่าที่ ร.ต., พ.ต.อ., Prof. — หน้าจอ/ใบพิมพ์ใช้ Patient::prefixLabel()
 */
class AddPrefixOtherToPatients extends Migration
{
    public function up()
    {
        Schema::table('patients', function (Blueprint $table) {
            if (! Schema::hasColumn('patients', 'prefix_other')) {
                $table->string('prefix_other', 50)->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('patients', function (Blueprint $table) {
            if (Schema::hasColumn('patients', 'prefix_other')) {
                $table->dropColumn('prefix_other');
            }
        });
    }
}
