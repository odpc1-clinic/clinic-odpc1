<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * สิ่งส่งตรวจวัณโรคได้หลายรายการต่อหนึ่งครั้ง (2569-09-29)
 *
 * ที่มา: คลินิกส่งตรวจมากกว่าหนึ่งอย่างในวันเดียวบ่อย เช่น เสมหะ Spot + Collect
 * หรือเสมหะ + น้ำลาย แต่ของเดิมเลือกได้ช่องเดียว
 *
 * เก็บเป็น JSON: [{"type":"sputum_spot","other":null,"date":"2569-09-29"}, ...]
 * ไม่แยกเป็นตารางใหม่ เพราะไม่มีรายงานไหนต้องนับสิ่งส่งตรวจแยกรายการ
 * และข้อมูลนี้อ่าน/เขียนพร้อมใบคัดกรองเสมอ
 *
 * คอลัมน์เดิม lab_specimen / lab_specimen_other / lab_sent_date ยังเก็บไว้
 * และยังถูกเขียน (เอารายการแรก) — ที่อื่นที่อ่านคอลัมน์เดิมอยู่จะได้ไม่พัง
 */
class AddLabSpecimensToTbScreenings extends Migration
{
    public function up()
    {
        Schema::table('tb_screenings', function (Blueprint $table) {
            $table->json('lab_specimens')->nullable()->after('lab_sent_date');
        });

        // ยกของเดิม (ถ้ามี) เข้ารูปแบบใหม่ — ตอนเขียน migration นี้ยังไม่มีข้อมูลเก่าเลย
        // แต่เขียนไว้เผื่อรันบนเครื่องอื่นที่มีข้อมูลแล้ว
        foreach (DB::table('tb_screenings')->whereNotNull('lab_specimen')->get() as $r) {
            DB::table('tb_screenings')->where('id', $r->id)->update([
                'lab_specimens' => json_encode([[
                    'type'  => $r->lab_specimen,
                    'other' => $r->lab_specimen_other,
                    'date'  => $r->lab_sent_date,
                ]], JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    public function down()
    {
        Schema::table('tb_screenings', function (Blueprint $table) {
            $table->dropColumn('lab_specimens');
        });
    }
}
