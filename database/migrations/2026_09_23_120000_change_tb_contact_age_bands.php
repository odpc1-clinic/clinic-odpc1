<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * เปลี่ยนช่วงอายุผู้สัมผัสในบัตร TB 01 ให้ตรงกับที่คลินิกใช้จริง (2569-09-23)
 *
 *   เดิม (ตามบัตรกระดาษ): < 5 ปี · 5-14 ปี
 *   ใหม่ (คลินิกขอ):      0-5 ปี · 6-17 ปี · 18 ปีขึ้นไป
 *
 * ย้ายค่าเดิมมาใส่ช่องใหม่ให้ด้วย (<5 -> 0-5 · 5-14 -> 6-17)
 * ตอนแก้ยังไม่มีทะเบียนจริงในระบบสักรายการ แต่เขียนย้ายข้อมูลไว้ให้ปลอดภัยถ้ารันซ้ำที่อื่น
 */
class ChangeTbContactAgeBands extends Migration
{
    public function up()
    {
        Schema::table('tb_registrations', function (Blueprint $table) {
            $table->unsignedSmallInteger('contact_age0_5')->nullable()->after('drug_allergy');
            $table->unsignedSmallInteger('contact_age0_5_screened')->nullable()->after('contact_age0_5');
            $table->unsignedSmallInteger('contact_age6_17')->nullable()->after('contact_age0_5_screened');
            $table->unsignedSmallInteger('contact_age6_17_screened')->nullable()->after('contact_age6_17');
            $table->unsignedSmallInteger('contact_age18up')->nullable()->after('contact_age6_17_screened');
            $table->unsignedSmallInteger('contact_age18up_screened')->nullable()->after('contact_age18up');
        });

        DB::table('tb_registrations')->update([
            'contact_age0_5'           => DB::raw('contact_under5'),
            'contact_age0_5_screened'  => DB::raw('contact_under5_screened'),
            'contact_age6_17'          => DB::raw('contact_5_14'),
            'contact_age6_17_screened' => DB::raw('contact_5_14_screened'),
        ]);

        Schema::table('tb_registrations', function (Blueprint $table) {
            $table->dropColumn(['contact_under5', 'contact_under5_screened', 'contact_5_14', 'contact_5_14_screened']);
        });
    }

    public function down()
    {
        Schema::table('tb_registrations', function (Blueprint $table) {
            $table->unsignedSmallInteger('contact_under5')->nullable();
            $table->unsignedSmallInteger('contact_under5_screened')->nullable();
            $table->unsignedSmallInteger('contact_5_14')->nullable();
            $table->unsignedSmallInteger('contact_5_14_screened')->nullable();
        });

        DB::table('tb_registrations')->update([
            'contact_under5'          => DB::raw('contact_age0_5'),
            'contact_under5_screened' => DB::raw('contact_age0_5_screened'),
            'contact_5_14'            => DB::raw('contact_age6_17'),
            'contact_5_14_screened'   => DB::raw('contact_age6_17_screened'),
        ]);

        Schema::table('tb_registrations', function (Blueprint $table) {
            $table->dropColumn([
                'contact_age0_5', 'contact_age0_5_screened',
                'contact_age6_17', 'contact_age6_17_screened',
                'contact_age18up', 'contact_age18up_screened',
            ]);
        });
    }
}
