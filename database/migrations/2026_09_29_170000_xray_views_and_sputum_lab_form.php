<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1) ใบส่งตรวจเอกซเรย์ — เก็บว่าถ่ายท่าไหนบ้าง อ่านผลทีละภาพ/วัน
 * 2) ผลเสมหะตามใบรายงานของห้องแล็บ (กระดาษสีเหลือง) — เลขที่ตลับ · ครั้งที่ · ลักษณะเสมหะ
 * คลินิกขอ 2569-09-29
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('tb_xrays', function (Blueprint $table) {
            $table->json('views')->nullable()->after('xray_no');          // ท่าที่ถ่าย
            $table->string('other_view', 120)->nullable()->after('views');
            $table->string('xn', 60)->nullable()->after('other_view');    // เลข XN ของห้องเอกซเรย์ (ผู้ป่วยเก่า)
            $table->date('read_date')->nullable()->after('result');       // วันที่อ่านผล (พ.ศ.)
            $table->string('reader', 120)->nullable()->after('read_date');
            $table->boolean('cd_to_pacs')->nullable()->after('reader');   // นำ CD จาก รพ.อื่น เข้า PACS
            $table->boolean('cd_write')->nullable()->after('cd_to_pacs'); // เขียน CD ให้ไปรักษาต่อ
        });

        Schema::table('tb_sputum_exams', function (Blueprint $table) {
            $table->string('sample_no', 60)->nullable()->after('lab_serial');   // เลขที่ตลับ
            $table->string('round_no', 20)->nullable()->after('sample_no');     // ครั้งที่
            $table->unsignedTinyInteger('appearance')->nullable()->after('round_no'); // ลักษณะเสมหะ 1-4
        });
    }

    public function down()
    {
        Schema::table('tb_xrays', function (Blueprint $table) {
            $table->dropColumn(['views', 'other_view', 'xn', 'read_date', 'reader', 'cd_to_pacs', 'cd_write']);
        });
        Schema::table('tb_sputum_exams', function (Blueprint $table) {
            $table->dropColumn(['sample_no', 'round_no', 'appearance']);
        });
    }
};
