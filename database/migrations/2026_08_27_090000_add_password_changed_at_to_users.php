<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * บันทึกว่าแต่ละบัญชีเปลี่ยนรหัสผ่านครั้งล่าสุดเมื่อไหร่ (2569-08-27)
 *
 * ที่มา: หนังสือ สธ 0418.17/719 ลว. 27 ส.ค. 2569 อ้างถึง สธ 0434.02/247
 * ให้เปลี่ยนรหัสผ่านทุกบัญชีให้แล้วเสร็จภายใน 28 ส.ค. 2569
 * เพราะ สกมช. ตรวจพบความเสี่ยงเรื่องชื่อผู้ใช้และรหัสผ่านของระบบในสังกัด สธ.
 *
 * ต้องมีช่องนี้เพื่อตอบหนังสือได้ว่า "ใครเปลี่ยนแล้วบ้าง" เป็นตัวเลข
 * ไม่ใช่ตอบลอย ๆ ว่าแจ้งให้เปลี่ยนแล้ว
 *
 * เก็บแค่ "เวลา" ไม่เก็บตัวรหัสผ่านเดิมเด็ดขาด
 */
class AddPasswordChangedAtToUsers extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('password_changed_at')->nullable()->after('password');

            // บังคับให้ตั้งรหัสใหม่ก่อนใช้งานต่อ — ใช้ตอนแอดมินรีเซ็ตให้คนที่ลืมรหัส
            $table->boolean('must_change_password')->default(false)->after('password_changed_at');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['password_changed_at', 'must_change_password']);
        });
    }
}
