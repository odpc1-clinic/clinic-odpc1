<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * บัตรวัณโรคแฝง (LTBI) แยกหน้าตาจาก TB 01 (คลินิกขอ 2569-10-27)
 * ช่องเฉพาะ LTBI: กลุ่มเป้าหมาย TPT · ผล TST/IGRA · CXR คัดแยกวัณโรคระยะลุกลาม
 * ผลการรักษาใช้คอลัมน์ outcome เดิม (รหัสชุด LTBI ดู TbRegistration::$ltbiOutcomeOption) ไม่ต้องเพิ่มคอลัมน์
 */
return new class extends Migration
{
    private array $cols = ['tpt_target', 'tpt_target_other', 'ltbi_test', 'ltbi_test_date', 'ltbi_test_result', 'tst_mm', 'cxr_date', 'cxr_result', 'cxr_note'];

    public function up(): void
    {
        if (Schema::hasColumn('tb_registrations', 'tpt_target')) { return; }
        Schema::table('tb_registrations', function (Blueprint $table) {
            $table->unsignedTinyInteger('tpt_target')->nullable()->after('index_registration_id');
            $table->string('tpt_target_other', 255)->nullable()->after('tpt_target');
            $table->unsignedTinyInteger('ltbi_test')->nullable()->after('tpt_target_other');
            $table->date('ltbi_test_date')->nullable()->after('ltbi_test');
            $table->unsignedTinyInteger('ltbi_test_result')->nullable()->after('ltbi_test_date');
            $table->decimal('tst_mm', 4, 1)->nullable()->after('ltbi_test_result');
            $table->date('cxr_date')->nullable()->after('tst_mm');
            $table->unsignedTinyInteger('cxr_result')->nullable()->after('cxr_date');
            $table->string('cxr_note', 255)->nullable()->after('cxr_result');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('tb_registrations', 'tpt_target')) { return; }
        Schema::table('tb_registrations', function (Blueprint $table) {
            $table->dropColumn($this->cols);
        });
    }
};
