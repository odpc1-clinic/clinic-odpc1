<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ประวัติการได้รับวัคซีน STIs ของคนไข้ (HPV, ไวรัสตับอักเสบบี/เอ, Mpox ฯลฯ) — ข้อความอิสระ (2569-10-21)
 * เก็บที่คนไข้ ไม่ใช่ visit: กรอกที่ห้องบัตร แล้วหน้าตรวจทุก visit เห็นและแก้ค่าเดียวกัน
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('patients', 'sti_vaccine_history')) {
            Schema::table('patients', function (Blueprint $table) {
                $table->text('sti_vaccine_history')->nullable()->after('drug_allergy');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('patients', 'sti_vaccine_history')) {
            Schema::table('patients', function (Blueprint $table) {
                $table->dropColumn('sti_vaccine_history');
            });
        }
    }
};
