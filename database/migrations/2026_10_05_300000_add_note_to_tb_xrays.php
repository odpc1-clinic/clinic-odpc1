<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * หมายเหตุของการถ่าย X-ray แต่ละครั้ง (2569-10-05)
 *
 * คลินิกเอาช่อง XN ออกจากบัตร TB 01 แล้วขอช่องหมายเหตุแทน — ใช้จดเรื่องที่เคยเป็นช่องติ๊ก
 * (นำ CD เข้า PACS / เขียน CD ไปรักษาต่อ) และที่ถ่ายจากที่อื่น
 */
class AddNoteToTbXrays extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('tb_xrays', 'note')) {
            Schema::table('tb_xrays', function (Blueprint $table) {
                $table->text('note')->nullable()->after('reader');
            });
        }
    }

    public function down()
    {
        Schema::table('tb_xrays', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
}
