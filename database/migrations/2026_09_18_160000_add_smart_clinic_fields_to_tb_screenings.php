<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ช่องเพิ่มตามลายมือในใบ Form 1 (Smart Clinic) ที่พี่กานต์ส่งมา 2569-09-18
 *
 * โรคประจำตัวเดิมเป็นช่องข้อความอิสระ แต่ในใบจริงติ๊กเป็นรายการ:
 *   ☐ ARV (ระบุสูตร)   ☐ DM (ระบุระดับ HbA1c)
 * ต้องแยกช่องเพราะสองโรคนี้มีผลต่อการรักษาวัณโรคโดยตรง และต้องเอาไปนับรายงานได้
 */
class AddSmartClinicFieldsToTbScreenings extends Migration
{
    public function up()
    {
        Schema::table('tb_screenings', function (Blueprint $table) {
            $table->boolean('has_arv')->default(false)->after('underlying_disease');
            $table->string('arv_detail')->nullable()->after('has_arv');
            $table->boolean('has_dm')->default(false)->after('arv_detail');
            // 1 = HbA1c < 7 · 2 = HbA1c ≥ 7 · 3 = ไม่ทราบระดับ
            $table->unsignedTinyInteger('dm_hba1c')->nullable()->after('has_dm');
        });
    }

    public function down()
    {
        Schema::table('tb_screenings', function (Blueprint $table) {
            $table->dropColumn(['has_arv', 'arv_detail', 'has_dm', 'dm_hba1c']);
        });
    }
}
