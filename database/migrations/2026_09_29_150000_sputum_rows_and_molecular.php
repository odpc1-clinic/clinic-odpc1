<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ตารางผลเสมหะ: เพิ่มช่อง Molecular และรองรับแถวที่ไม่ใช่ "เดือนเต็ม" เช่น 2wk
 *
 * ของเดิมหนึ่งเดือนมีได้แถวเดียว (month_no) แต่งานจริงมีตรวจที่ 2 สัปดาห์
 * และบางเดือนตรวจซ้ำหลายครั้ง จึงเพิ่ม label ไว้บอกว่าแถวนั้นคือรอบไหน
 * month_no ยังอยู่เพื่อใช้เรียงและไฮไลต์เดือนที่ต้องตรวจตามสูตร (คลินิกขอ 2569-09-29)
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('tb_sputum_exams', function (Blueprint $table) {
            $table->string('label', 20)->nullable()->after('month_no');     // 0 · 2wk · 1 · 2 ...
            $table->string('molecular', 120)->nullable()->after('culture'); // ผล Xpert/LPA/PCR
            $table->unsignedSmallInteger('sort')->default(0)->after('molecular');
        });

        // แถวเดิมใช้เลขเดือนเป็นป้ายกำกับ และเรียงตามเดือน
        foreach (DB::table('tb_sputum_exams')->get(['id', 'month_no']) as $row) {
            DB::table('tb_sputum_exams')->where('id', $row->id)
                ->update(['label' => (string) $row->month_no, 'sort' => (int) $row->month_no * 10]);
        }
    }

    public function down()
    {
        Schema::table('tb_sputum_exams', function (Blueprint $table) {
            $table->dropColumn(['label', 'molecular', 'sort']);
        });
    }
};
