<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ขยายตารางผู้สัมผัสร่วมบ้าน ให้ตรงกับตารางที่คลินิกใช้จริง (พี่กานต์ส่งตาราง 2569-09-18)
 *   ลำดับ · ชื่อ-สกุล · อายุ · ความสัมพันธ์ · อาการ · ผล X-ray · ผลเสมหะ · ผล IGRA/Skin test · สถานะการรักษา · หมายเหตุ
 *
 * ของเดิมเก็บแค่ตอน "คัดกรอง" แต่ของจริงต้องตามผลตรวจของผู้สัมผัสแต่ละคนต่อจนจบ
 * (ผู้สัมผัสที่ผล IGRA บวกแต่ไม่มีอาการ = LTBI ซึ่งเป็นที่มาของทะเบียน LTBI 10 รายที่คลินิกดูแลอยู่)
 *
 * และ Family Tree (Form 2 ข้อ 6) — เก็บเป็นรูปอัปโหลด เข้ารหัสเหมือนรูปคนไข้
 */
class ExpandTbContactScreening extends Migration
{
    public function up()
    {
        Schema::table('tb_household_members', function (Blueprint $table) {
            $table->string('symptoms')->nullable()->after('relation');          // อาการ
            $table->string('cxr_result')->nullable()->after('symptoms');        // ผลตรวจ X-ray
            $table->string('sputum_result')->nullable()->after('cxr_result');   // ผลตรวจเสมหะ
            $table->string('igra_result')->nullable()->after('sputum_result');  // ผล IGRA / Skin test (TST)
            $table->string('treatment_status')->nullable()->after('igra_result'); // สถานะการรักษา
            $table->string('note')->nullable()->after('treatment_status');
        });

        Schema::table('tb_screenings', function (Blueprint $table) {
            // ชื่อไฟล์รูปผังครอบครัว — ไฟล์จริงเก็บเข้ารหัสไว้ที่ storage/app/private/tb_family_tree
            $table->string('family_tree_photo')->nullable()->after('ct_other');
        });
    }

    public function down()
    {
        Schema::table('tb_household_members', function (Blueprint $table) {
            $table->dropColumn(['symptoms', 'cxr_result', 'sputum_result', 'igra_result', 'treatment_status', 'note']);
        });
        Schema::table('tb_screenings', function (Blueprint $table) {
            $table->dropColumn('family_tree_photo');
        });
    }
}
