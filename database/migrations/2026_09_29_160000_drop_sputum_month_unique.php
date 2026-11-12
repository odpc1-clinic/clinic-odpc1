<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * เอา unique (บัตร, เดือน) ของตารางผลเสมหะออก
 *
 * ของเดิมบังคับให้หนึ่งเดือนมีได้แถวเดียว แต่งานจริงมีรอบ 2wk และบางเดือนตรวจซ้ำ
 * เปลี่ยนเป็น index ธรรมดาเพื่อให้ยังค้นเร็วเหมือนเดิม (2569-09-29)
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('tb_sputum_exams', function (Blueprint $table) {
            $table->dropUnique('tb_sputum_reg_month_unique');
            $table->index(['tb_registration_id', 'month_no'], 'tb_sputum_reg_month_idx');
        });
    }

    public function down()
    {
        Schema::table('tb_sputum_exams', function (Blueprint $table) {
            $table->dropIndex('tb_sputum_reg_month_idx');
            $table->unique(['tb_registration_id', 'month_no'], 'tb_sputum_reg_month_unique');
        });
    }
};
