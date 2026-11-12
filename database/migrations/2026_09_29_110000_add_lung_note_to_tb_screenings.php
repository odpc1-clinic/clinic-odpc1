<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** ข้อความบรรยายผลตรวจปอด (เขียนคู่กับรูปที่มาร์ค) — คลินิกขอ 2569-09-29 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('tb_screenings', function (Blueprint $table) {
            $table->text('lung_note')->nullable()->after('lung_marks');
        });
    }

    public function down()
    {
        Schema::table('tb_screenings', function (Blueprint $table) {
            $table->dropColumn('lung_note');
        });
    }
};
