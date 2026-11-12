<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lab ส่งตรวจภายนอกที่ไม่ใช่ N Health — คลินิกวัณโรคจะส่ง NAT2, WGS, TB-LAMP (2569-09-30)
 * ยังไม่รู้ว่าส่งแล็บไหน จึงลงแล็บเป็น "ยังไม่ระบุแล็บ" ไว้ก่อน แก้ชื่อแล็บได้ที่หน้าแคตตาล็อก Lab นอก
 *
 * visit_external_labs.vendor — เก็บชื่อแล็บตอนสั่ง (snapshot) เพราะแคตตาล็อกแก้ชื่อแล็บทีหลังได้
 * ใบที่สั่งไปแล้วต้องยังบอกได้ว่าส่งไปที่ไหน · ของเดิมทั้งหมดเป็น N Health
 */
class ExternalLabVendorsTbTests extends Migration
{
    public function up()
    {
        Schema::table('visit_external_labs', function (Blueprint $table) {
            $table->string('vendor', 255)->nullable()->after('external_lab_test_id');
        });
        DB::statement('UPDATE visit_external_labs v JOIN external_lab_tests t ON t.id = v.external_lab_test_id SET v.vendor = t.vendor');

        $now = now();
        foreach ([
            ['TB-NAT2', 'NAT2 genotyping (INH acetylator status)',              'เลือด EDTA'],
            ['TB-WGS',  'Whole Genome Sequencing (WGS) — M. tuberculosis',       'เสมหะ / เชื้อจาก culture'],
            ['TB-LAMP', 'TB-LAMP',                                              'เสมหะ'],
        ] as [$code, $name, $specimen]) {
            if (DB::table('external_lab_tests')->where('code', $code)->exists()) { continue; }
            DB::table('external_lab_tests')->insert([
                'vendor' => 'ยังไม่ระบุแล็บ', 'code' => $code, 'name' => $name, 'specimen' => $specimen,
                'turnaround' => null, 'price' => null, 'active' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down()
    {
        DB::table('external_lab_tests')->whereIn('code', ['TB-NAT2', 'TB-WGS', 'TB-LAMP'])->delete();
        Schema::table('visit_external_labs', function (Blueprint $table) { $table->dropColumn('vendor'); });
    }
}
