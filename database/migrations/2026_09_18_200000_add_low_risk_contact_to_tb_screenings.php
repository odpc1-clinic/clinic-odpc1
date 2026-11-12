<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ลักษณะการสัมผัสข้อ 3.3 "ผู้สัมผัสความเสี่ยงต่ำ" (คลินิกขอ 2569-09-18)
 *
 * แยกช่องของตัวเอง ไม่รวมกับช่อง "อื่นๆ" เพราะเป็นระดับความเสี่ยงที่ใช้ตัดสินใจว่า
 * ต้องส่งตรวจ/ติดตามแค่ไหน และต้องนับรายงานแยกจากผู้สัมผัสใกล้ชิด
 */
class AddLowRiskContactToTbScreenings extends Migration
{
    public function up()
    {
        Schema::table('tb_screenings', function (Blueprint $table) {
            $table->boolean('ct_low_risk')->default(false)->after('ct_close_120h');
        });
    }

    public function down()
    {
        Schema::table('tb_screenings', function (Blueprint $table) {
            $table->dropColumn('ct_low_risk');
        });
    }
}
