<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * สมุดเหลือง "ครั้งนี้" ของแต่ละ visit TMC (คลินิกขอ 2569-11-04 — พยาบาลงงขั้นตอนออกเล่ม)
 *
 * เดิมมีแค่เลขเล่มปัจจุบันที่ตัวคนไข้ + ปุ่มออกเล่มใหม่ รายงานต้องเดาเอาจากประวัติการแก้เลข
 * และแยกไม่ได้ว่าเล่มเดิมเป็นของเราหรือของที่อื่น
 * ตอนนี้ให้เลือกตรง ๆ ทุกครั้งที่มา: ใช้เล่มเดิม / ออกใหม่ / ออกทดแทน (ชำรุด-หาย) / สมุดเสีย / ไม่ใช้สมุด
 *
 * tmc_book_action    old | new | replace | spoiled | none
 * tmc_book_no        เลขเล่มที่คนไข้ถือกลับไป (เล่มเดิมที่ใช้ หรือเล่มใหม่ที่ออกให้)
 * tmc_book_old_no    เล่มเดิมที่ชำรุด/หาย หรือเล่มเปล่าของเราที่เขียนเสีย
 * tmc_book_old_src   ours | other — เล่มเดิมออกโดยเราหรือที่อื่น
 * tmc_book_old_place ชื่อสถานที่ที่ออกเล่มเดิม (เมื่อเป็นของที่อื่น)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('visits', 'tmc_book_action')) { return; }
        Schema::table('visits', function (Blueprint $t) {
            $t->string('tmc_book_action', 20)->nullable();
            $t->string('tmc_book_no', 50)->nullable();
            $t->string('tmc_book_old_no', 50)->nullable();
            $t->string('tmc_book_old_src', 10)->nullable();
            $t->string('tmc_book_old_place', 150)->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('visits', 'tmc_book_action')) { return; }
        Schema::table('visits', function (Blueprint $t) {
            $t->dropColumn(['tmc_book_action', 'tmc_book_no', 'tmc_book_old_no', 'tmc_book_old_src', 'tmc_book_old_place']);
        });
    }
};
