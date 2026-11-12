<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ถอนคอลัมน์ external ออกจาก lab_subs
 * เหตุผล: Lab ส่งตรวจภายนอกถูกแยกไปเป็นโมดูลของตัวเองแล้ว (external_lab_tests / visit_external_labs)
 * ธงนี้จึงซ้ำซ้อนและทำให้สับสนว่าต้องไปติ๊กที่ไหน (ตรวจแล้วไม่มีข้อมูลจริงใช้งานเลย)
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('lab_subs', function (Blueprint $table) {
            $table->dropColumn(['external', 'external_source']);
        });
    }

    public function down()
    {
        Schema::table('lab_subs', function (Blueprint $table) {
            $table->boolean('external')->default(false)->after('result');
            $table->string('external_source')->nullable()->after('external');
        });
    }
};
