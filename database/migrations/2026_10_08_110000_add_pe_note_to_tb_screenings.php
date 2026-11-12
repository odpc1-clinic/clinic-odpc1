<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ช่องบันทึกอิสระของแพทย์ ใต้ "ตรวจร่างกาย (วัณโรค)" — คลินิก TB ขอ 2569-10-08
 * ช่อง GA/HEENT/... เป็นช่องสั้นรายระบบ หมอต้องการที่เขียนข้อความยาว ๆ อะไรก็ได้
 */
class AddPeNoteToTbScreenings extends Migration
{
    public function up()
    {
        Schema::table('tb_screenings', function (Blueprint $table) {
            if (! Schema::hasColumn('tb_screenings', 'pe_note')) {
                $table->text('pe_note')->nullable()->after('pe_neuro');
            }
        });
    }

    public function down()
    {
        Schema::table('tb_screenings', function (Blueprint $table) {
            if (Schema::hasColumn('tb_screenings', 'pe_note')) {
                $table->dropColumn('pe_note');
            }
        });
    }
}
