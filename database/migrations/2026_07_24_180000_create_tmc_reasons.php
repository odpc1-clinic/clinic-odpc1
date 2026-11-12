<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * รายการ "สาเหตุการมาที่คลินิก" TMC แบบเพิ่มเองได้ (master list ใช้ร่วมกัน)
 * - tmc_reasons: รายการตัวเลือก (name ไม่ซ้ำ — กันเพิ่มซ้ำแบบ Medivel)
 * - visits.tmc_reason: ค่าที่เลือกของ visit นั้น (ชื่อคั่นด้วย comma)
 */
class CreateTmcReasons extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('tmc_reasons')) {
            Schema::create('tmc_reasons', function (Blueprint $table) {
                $table->id();
                $table->string('name', 191)->unique();
                $table->timestamps();
            });
        }

        Schema::table('visits', function (Blueprint $table) {
            if (!Schema::hasColumn('visits', 'tmc_reason')) {
                $table->text('tmc_reason')->nullable()->after('tmc_reason_vaccine');
            }
        });

        // ค่าตั้งต้นตามใบซักประวัติ TMC
        foreach (['มาลาเรียและการป้องกัน', 'การขึ้นที่สูง/เมาการเดินทาง', 'ต้องการฉีดวัคซีน'] as $name) {
            DB::table('tmc_reasons')->updateOrInsert(
                ['name' => $name],
                ['updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    public function down()
    {
        Schema::table('visits', function (Blueprint $table) {
            if (Schema::hasColumn('visits', 'tmc_reason')) {
                $table->dropColumn('tmc_reason');
            }
        });
        Schema::dropIfExists('tmc_reasons');
    }
}
