<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ผลเสมหะ Collect ครั้งที่ 2 (2569-10-02)
 *
 * ที่มา: คลินิกเก็บเสมหะ Spot 1 ครั้ง + Collect (ตอนเช้า) 2 ครั้ง แต่ตารางในบัตร TB 01 มีช่อง Collect ช่องเดียว
 * ช่องเดิม collect = Collect ครั้งที่ 1 · เพิ่ม collect2 ต่อท้าย ข้อมูลเดิมไม่ต้องย้าย
 */
class AddCollect2ToTbSputumExams extends Migration
{
    public function up()
    {
        Schema::table('tb_sputum_exams', function (Blueprint $table) {
            $table->string('collect2', 20)->nullable()->after('collect');
        });
    }

    public function down()
    {
        Schema::table('tb_sputum_exams', function (Blueprint $table) {
            $table->dropColumn('collect2');
        });
    }
}
