<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * เคสเก่าจากทะเบียนกระดาษของคลินิกวัณโรค (คลินิกขอ 2569-11-05)
 *
 * รายงานวัณโรคแยกผู้ป่วยใหม่/เก่าจาก visit คลินิก 2 ครั้งแรกในระบบ — คนที่เคยมาตอนยังลงกระดาษ
 * พอมาครั้งแรกในระบบจะถูกนับเป็น "ใหม่" ผิด · ติ๊กไว้ที่ตัวคนไข้ = นับเป็นผู้ป่วยเก่าเสมอ
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('patients', 'tb_paper_old')) { return; }
        Schema::table('patients', function (Blueprint $t) {
            $t->boolean('tb_paper_old')->default(false);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('patients', 'tb_paper_old')) { return; }
        Schema::table('patients', function (Blueprint $t) {
            $t->dropColumn('tb_paper_old');
        });
    }
};
