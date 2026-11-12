<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ช่อง "การตรวจ HIV" (บริการ) ของทะเบียน ContactSTI
 * เช่น on Prep / on ARV / SDR / Advice / จะไปตรวจตามสิทธิ์ / ตรวจแล้ว / อื่นๆ
 * (ผล + วันที่ตรวจจริง ยังใช้ของ visit เดิม)
 */
class AddHivServiceToContactSti extends Migration
{
    public function up()
    {
        Schema::table('contact_sti', function (Blueprint $table) {
            if (!Schema::hasColumn('contact_sti', 'hiv_service')) {
                $table->string('hiv_service', 40)->nullable()->after('note');
            }
        });
    }

    public function down()
    {
        Schema::table('contact_sti', function (Blueprint $table) {
            if (Schema::hasColumn('contact_sti', 'hiv_service')) {
                $table->dropColumn('hiv_service');
            }
        });
    }
}
