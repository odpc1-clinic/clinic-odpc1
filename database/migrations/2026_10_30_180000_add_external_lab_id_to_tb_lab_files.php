<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ผูกไฟล์ผลตรวจเข้ากับรายการ Lab ส่งตรวจนอกที่หมอสั่ง (คลินิกขอ 2569-10-30)
 *
 * เดิมไฟล์ผล (tb_lab_files) กับรายการสั่งตรวจ (visit_external_labs) แยกกัน — อัปไฟล์แล้วรายการยังขึ้น "รอผล"
 * พยาบาลต้องพิมพ์ผลซ้ำอีกรอบ · ผูกด้วย external_lab_id แล้วแถวสั่งตรวจโชว์ไฟล์แทนคำว่ารอผล
 * nullable: ไฟล์ทั่วไปที่ไม่ได้มาจากใบสั่ง (ผลที่คนไข้ถือมาเอง) ยังอัปแบบเดิมได้
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_lab_files', function (Blueprint $t) {
            $t->unsignedBigInteger('external_lab_id')->nullable()->after('visit_id');
            $t->index('external_lab_id');
        });
    }

    public function down(): void
    {
        Schema::table('tb_lab_files', function (Blueprint $t) {
            $t->dropIndex(['external_lab_id']);
            $t->dropColumn('external_lab_id');
        });
    }
};
