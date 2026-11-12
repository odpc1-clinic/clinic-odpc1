<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * งาน TMC รอบ 2569-09-30
 *
 * service_prices.clinics — ค่าบริการแต่ละตัวใช้กับคลินิกไหน ("1,2" = STI + วัณโรค) · ว่าง = ทุกคลินิก
 *   คลินิก TMC ขอให้ซ่อนรายการของ STI (จี้หูด, คัดกรองกลุ่มเป้าหมาย, แพ็กเกจ STD)
 *   ตั้งเป็น "1,2" ไม่ใช่ "1" — คลินิกวัณโรคยังเห็นเหมือนเดิม ไม่มีใครขอให้เปลี่ยน
 *
 * patients.email — หน้าลงทะเบียน + ช่องทางติดตามผู้ป่วยทางอีเมล (คนไข้ TMC ต่างชาติหลายรายติดต่อทางอีเมล)
 *
 * visits.tmc_pe — การตรวจร่างกายแบบ "normal all + สวิตช์ระบบที่ผิดปกติ" ของ TMC
 *   {"normal_all":1,"sys":{"heent":"...", ...},"note":"..."} · ระบบประกอบข้อความลง body_check ให้ด้วย
 *   ใบพิมพ์/ประวัติเดิมที่อ่าน body_check จึงยังใช้ได้
 */
class TmcServicesEmailPe extends Migration
{
    public function up()
    {
        Schema::table('service_prices', function (Blueprint $table) {
            $table->string('clinics', 20)->nullable()->after('is_package');
        });
        DB::table('service_prices')->whereIn('id', [3, 4, 5, 6])->update(['clinics' => '1,2']);

        Schema::table('patients', function (Blueprint $table) {
            $table->string('email', 191)->nullable()->after('tel');
        });

        Schema::table('visits', function (Blueprint $table) {
            $table->json('tmc_pe')->nullable()->after('body_check');
        });
    }

    public function down()
    {
        Schema::table('visits', function (Blueprint $table) { $table->dropColumn('tmc_pe'); });
        Schema::table('patients', function (Blueprint $table) { $table->dropColumn('email'); });
        Schema::table('service_prices', function (Blueprint $table) { $table->dropColumn('clinics'); });
    }
}
