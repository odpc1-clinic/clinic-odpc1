<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ผลเอกซเรย์ปอดหลายแถวต่อหนึ่งครั้งที่มา (2569-10-02)
 *
 * ที่มา: วันเดียวถ่ายหลายท่า (PA + Lateral ฯลฯ) แต่ใบคัดกรองเดิมลงผลได้ช่องเดียว
 * ตาราง tb_xrays ใช้ไม่ได้ เพราะผูกกับบัตร TB 01 (tb_registration_id NOT NULL) — คนที่มาคัดกรองยังไม่มีบัตร
 *
 * เก็บเป็น JSON: [{"view":"pa_upright","result":2,"note":"RUL infiltration"}, ...]
 *   view   = คีย์ของ TbXray::$viewOption (ท่าถ่ายชุดเดียวกับบัตร TB 01) หรือ null
 *   result = คีย์ของ TbScreening::$cxrOption หรือ null
 * แบบเดียวกับ lab_specimens — คอลัมน์เดิม cxr_result / cxr_note ยังเก็บ และยังถูกเขียน (เอาแถวแรก)
 */
class AddCxrResultsToTbScreenings extends Migration
{
    public function up()
    {
        Schema::table('tb_screenings', function (Blueprint $table) {
            $table->json('cxr_results')->nullable()->after('cxr_note');
        });

        // ยกของเดิมเข้ารูปแบบใหม่
        foreach (DB::table('tb_screenings')->where(fn ($q) => $q->whereNotNull('cxr_result')->orWhereNotNull('cxr_note'))->get() as $r) {
            DB::table('tb_screenings')->where('id', $r->id)->update([
                'cxr_results' => json_encode([[
                    'view'   => null,
                    'result' => $r->cxr_result !== null ? (int) $r->cxr_result : null,
                    'note'   => $r->cxr_note,
                ]], JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    public function down()
    {
        Schema::table('tb_screenings', function (Blueprint $table) {
            $table->dropColumn('cxr_results');
        });
    }
}
