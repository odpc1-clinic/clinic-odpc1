<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HN ของผู้สัมผัสร่วมบ้าน (คลินิกขอ 2569-09-18)
 *
 * ช่องนี้ว่าง = คนในรายชื่อ "ยังไม่ได้มาตรวจ" ที่คลินิก จึงยังไม่มี HN
 * ใช้เป็นตัวติดตามว่าผู้สัมผัสรายไหนยังตามมาตรวจไม่ได้
 *
 * patient_id (มีอยู่แล้ว) จะถูกเติมให้อัตโนมัติเมื่อ HN ตรงกับคนไข้ในระบบ
 * เพื่อให้กดข้ามไปดูประวัติของคนนั้นได้เลย
 */
class AddHnToTbHouseholdMembers extends Migration
{
    public function up()
    {
        Schema::table('tb_household_members', function (Blueprint $table) {
            $table->string('hn', 30)->nullable()->after('name')->index();
        });
    }

    public function down()
    {
        Schema::table('tb_household_members', function (Blueprint $table) {
            $table->dropColumn('hn');
        });
    }
}
