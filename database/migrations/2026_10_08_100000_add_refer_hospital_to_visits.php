<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ปลายทางการส่งต่อ (Refer) ของการมาตรวจครั้งนั้น — คลินิก TB ขอ 2569-10-08
 *
 * แยกจากห้อง/สถานะ (ช่องติ๊ก Refer + ชื่อ รพ. ข้างปุ่มบันทึก) — มีค่า = ครั้งนี้ refer, ว่าง = ไม่ได้ refer
 */
class AddReferHospitalToVisits extends Migration
{
    public function up()
    {
        Schema::table('visits', function (Blueprint $table) {
            if (! Schema::hasColumn('visits', 'refer_hospital')) {
                $table->string('refer_hospital', 255)->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('visits', function (Blueprint $table) {
            if (Schema::hasColumn('visits', 'refer_hospital')) {
                $table->dropColumn('refer_hospital');
            }
        });
    }
}
