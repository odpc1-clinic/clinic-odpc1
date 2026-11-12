<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ประเภทการวินิจฉัยของแต่ละรหัส (คลินิกวัณโรคขอ 2569-10-01)
 *   dx_type = principal (โรคหลัก) · comorbidity (โรคร่วม) · complication (โรคแทรกซ้อน) · other (อื่นๆ)
 *   custom_text = โรคที่หมอพิมพ์เอง ไม่มีในรายการ ICD-10 ของระบบ (แถวนั้น code เป็น NULL)
 * แถวเดิมที่มีอยู่: รหัสหลัก = principal · ที่เหลือ = other
 * code ต้องเป็น NULL ได้ (แก้ด้วย SQL ตรง เพราะเครื่องนี้ไม่มี doctrine/dbal สำหรับ ->change())
 */
class AddDxTypeToIcd10Entries extends Migration
{
    public function up()
    {
        Schema::table('icd10_entries', function (Blueprint $table) {
            $table->string('dx_type', 20)->nullable()->after('is_primary');
            $table->string('custom_text', 255)->nullable()->after('dx_type');
        });
        DB::statement('ALTER TABLE icd10_entries MODIFY code VARCHAR(10) NULL');
        DB::table('icd10_entries')->update(['dx_type' => DB::raw("IF(is_primary = 1, 'principal', 'other')")]);
    }

    public function down()
    {
        DB::table('icd10_entries')->whereNull('code')->delete();
        DB::statement('ALTER TABLE icd10_entries MODIFY code VARCHAR(10) NOT NULL');
        Schema::table('icd10_entries', function (Blueprint $table) {
            $table->dropColumn(['dx_type', 'custom_text']);
        });
    }
}
