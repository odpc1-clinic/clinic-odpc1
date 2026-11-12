<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * เล่มเปล่าที่เขียนเสียไปก่อนได้เล่มที่ให้คนไข้ (คลินิกเจอ 2569-11-06 — ออกเล่มแรกแต่เขียนผิด 2 เล่ม)
 * visit หนึ่งเสียได้หลายเล่ม · เก็บเลขคั่นจุลภาค · รายงาน TMC นับเป็นสมุดเสียทีละเล่ม เข้ารายการส่งทำลาย
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('visits', 'tmc_book_spoiled_nos')) { return; }
        Schema::table('visits', function (Blueprint $t) {
            $t->string('tmc_book_spoiled_nos', 255)->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('visits', 'tmc_book_spoiled_nos')) { return; }
        Schema::table('visits', function (Blueprint $t) {
            $t->dropColumn('tmc_book_spoiled_nos');
        });
    }
};
