<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * สถานะ DOT ของคนไข้ — ใช้กับหน้ารวมคนไข้ DOT รายวัน
 *
 * คนไข้ที่ "Off DOT" (เช่น ผล AFB เป็นลบแล้ว ไม่ต้องกำกับกินยาทุกวัน)
 * ไม่ได้จบการรักษา ยังต้องนัด F/U ตาม road map อยู่ จึงไม่ใช้ช่อง outcome
 * แค่พักออกจากรายชื่อรายวัน และดึงกลับมาได้ถ้าต้องกำกับอีก (2569-09-28)
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('tb_registrations', function (Blueprint $table) {
            $table->boolean('dot_active')->default(true)->after('dot_by_other');
            $table->date('dot_off_date')->nullable()->after('dot_active');
            $table->string('dot_off_reason', 255)->nullable()->after('dot_off_date');
            $table->unsignedBigInteger('dot_off_by')->nullable()->after('dot_off_reason');
        });
    }

    public function down()
    {
        Schema::table('tb_registrations', function (Blueprint $table) {
            $table->dropColumn(['dot_active', 'dot_off_date', 'dot_off_reason', 'dot_off_by']);
        });
    }
};
