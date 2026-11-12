<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ผลทดสอบความไวต่อยาของเชื้อหนองใน (GC AST) — ลงเมื่อ Culture = Growth / GC Growth (ห้อง Lab ขอ 2569-09-30)
 * เก็บเป็น JSON บนรายการ Culture ตัวนั้น: {"drugs":{"cefixime":{"v":"0.016","i":"S"},...},"comment":"...","by":18,"at":"..."}
 * เก็บผลแปลความ (S/I/R) ที่ห้อง Lab ยืนยันไว้ด้วย — เกณฑ์ CLSI เปลี่ยนทุกปี ผลเก่าต้องคงตามเกณฑ์ตอนที่ออกผล
 */
class AddAstToLabSubs extends Migration
{
    public function up()
    {
        Schema::table('lab_subs', function (Blueprint $table) {
            $table->json('ast')->nullable()->after('result');
        });
    }

    public function down()
    {
        Schema::table('lab_subs', function (Blueprint $table) {
            $table->dropColumn('ast');
        });
    }
}
