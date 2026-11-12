<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * หน้าติดตาม DOT — ผู้รับผิดชอบเคส + บันทึกส่งเวร (พยาบาลขอ 2569-09-30)
 *
 * dot_owner: คนไข้บางรายมีเจ้าหน้าที่รับเป็นเคสประจำ คนอยู่เวรไม่ใช่คน DOT ต้องรู้ว่าจะส่งต่อให้ใคร
 * tb_dot_handovers: เรื่องที่ต้องส่งเวรของคนไข้แต่ละราย ต่างจากหมายเหตุรายวัน (tb_dot_days.note)
 *   ตรงที่ค้างโชว์ทุกครั้งที่เปิดหน้า DOT จนกว่าจะมีคนติ๊กว่า "ปัญหาสิ้นสุด" — เก็บประวัติไว้ ไม่ลบ
 */
class AddDotOwnerAndHandovers extends Migration
{
    public function up()
    {
        Schema::table('tb_registrations', function (Blueprint $table) {
            $table->string('dot_owner', 100)->nullable()->after('dot_channel');
        });

        Schema::create('tb_dot_handovers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tb_registration_id')->index();
            $table->string('note', 500);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('tb_dot_handovers');
        Schema::table('tb_registrations', function (Blueprint $table) {
            $table->dropColumn('dot_owner');
        });
    }
}
