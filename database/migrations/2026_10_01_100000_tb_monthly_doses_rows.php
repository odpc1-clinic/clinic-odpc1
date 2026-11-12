<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ตารางขนาดยา TB 01: หนึ่งแถว = การสั่งยาหนึ่งครั้ง (ไม่ใช่หนึ่งเดือน) — คลินิกขอ 2569-10-01
 * มีรอบ "2 สัปดาห์" เหมือนตารางเสมหะ และนัดนอกรอบได้ (เช่น หมอนัดมาปรับยาวันที่ 2)
 *
 * label = ป้ายของแถว ("0", "2wk", "1" ... หรือข้อความเอง เช่น "วันที่ 2")
 * sort  = ตำแหน่งตามเวลา × 100 (TbMonthlyDose::sortValue) ใช้เรียงและหา "การสั่งยาครั้งล่าสุด"
 * เลิก unique (บัตร, เดือน) เพราะเดือนเดียวมีได้หลายแถว
 */
class TbMonthlyDosesRows extends Migration
{
    public function up()
    {
        Schema::table('tb_monthly_doses', function (Blueprint $table) {
            $table->string('label', 30)->nullable()->after('month_no');
            $table->unsignedSmallInteger('sort')->default(0)->after('label');
        });

        // แถวเดิมทั้งหมดเป็นแถวรายเดือน
        DB::table('tb_monthly_doses')->update([
            'label' => DB::raw('CAST(month_no AS CHAR)'),
            'sort'  => DB::raw('month_no * 100'),   // = TbMonthlyDose::sortValue() ของแถวรายเดือน
        ]);

        Schema::table('tb_monthly_doses', function (Blueprint $table) {
            $table->dropUnique('tb_dose_reg_month_unique');
            $table->index(['tb_registration_id', 'month_no'], 'tb_dose_reg_month_idx');
        });
    }

    public function down()
    {
        Schema::table('tb_monthly_doses', function (Blueprint $table) {
            $table->dropIndex('tb_dose_reg_month_idx');
            $table->unique(['tb_registration_id', 'month_no'], 'tb_dose_reg_month_unique');
            $table->dropColumn(['label', 'sort']);
        });
    }
}
