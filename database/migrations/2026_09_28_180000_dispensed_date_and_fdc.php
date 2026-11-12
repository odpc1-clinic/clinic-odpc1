<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 1) วันที่สั่งยาของแต่ละเดือนในตารางขนาดยา
 * 2) ยืนยันว่า "ไม่มีผู้สัมผัสร่วมบ้าน" ให้ต่างจาก "ยังไม่ได้ถาม"
 * 3) สูตรมาตรฐานแบบเม็ดรวม (FDC) — ของเดิมมีแต่แบบแยกเม็ด
 *    คลินิกขอ 2569-09-28
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('tb_monthly_doses', function (Blueprint $table) {
            $table->date('dispensed_date')->nullable()->after('weight');   // พ.ศ. — วันที่จ่าย/สั่งยาของเดือนนั้น
        });

        // null = ยังไม่ได้ถาม · 1 = ถามแล้วและยืนยันว่าไม่มีผู้สัมผัสร่วมบ้าน
        Schema::table('tb_registrations', function (Blueprint $table) {
            $table->boolean('no_household_contact')->nullable()->after('contact_age18up_screened');
        });

        // สูตรเม็ดรวม — จำนวนเม็ดคิดตามน้ำหนัก เลือกแล้วช่องยาในตารางจะเป็น FDC ช่องเดียว
        $now = now();
        foreach ([
            ['code' => 'R08', 'name' => 'DS-TB (2HRZE เม็ดรวม 4-FDC)',
             'category' => 'ไวต่อยา · ช่วงเข้มข้น แบบเม็ดรวม', 'regimen' => '2HRZE (FDC)',
             'drugs' => ['4FDC' => 1], 'sort' => 108],
            ['code' => 'R09', 'name' => 'DS-TB (4HR เม็ดรวม 2-FDC)',
             'category' => 'ไวต่อยา · ช่วงประคับประคอง แบบเม็ดรวม', 'regimen' => '4HR (FDC)',
             'drugs' => ['2FDC' => 1], 'sort' => 109],
        ] as $r) {
            if (DB::table('tb_regimens')->where('code', $r['code'])->exists()) { continue; }
            DB::table('tb_regimens')->insert([
                'code' => $r['code'], 'name' => $r['name'], 'category' => $r['category'],
                'regimen' => $r['regimen'], 'drugs' => json_encode($r['drugs']),
                'sort' => $r['sort'], 'active' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // เกณฑ์จำนวนเม็ดตามน้ำหนัก (แนวทางที่ใช้กับ 4-FDC/2-FDC ของผู้ใหญ่)
        // เก็บเป็นข้อความ "x เม็ด/วัน" เพราะไม่ใช่ขนาด มก. — พยาบาลตรวจทานได้จากหน้าตั้งค่าเกณฑ์
        $bands = [
            ['min' => 30, 'max' => 37.9, 'tabs' => 2],
            ['min' => 38, 'max' => 54.9, 'tabs' => 3],
            ['min' => 55, 'max' => 70.9, 'tabs' => 4],
            ['min' => 71, 'max' => null, 'tabs' => 5],
        ];
        $sort = 1030;
        foreach ([
            '4FDC' => '4-FDC (H75-R150-Z400-E275)',
            '2FDC' => '2-FDC (H75-R150)',
        ] as $key => $label) {
            if (DB::table('tb_dose_rules')->where('drug_key', $key)->exists()) { continue; }
            foreach ($bands as $b) {
                DB::table('tb_dose_rules')->insert([
                    'group' => 'DS', 'patient_type' => 'adult',
                    'drug_key' => $key, 'drug_label' => $label, 'strength' => 'เม็ดรวม',
                    'weight_min' => $b['min'], 'weight_max' => $b['max'],
                    'dose_mg' => null, 'dose_text' => $b['tabs'] . ' เม็ด/วัน',
                    'note' => 'จำนวนเม็ดตามน้ำหนัก — ตรวจทานกับแนวทางของคลินิกก่อนใช้',
                    'sort' => $sort++, 'active' => 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        echo "  เพิ่มวันที่สั่งยา · ช่องยืนยันไม่มีผู้สัมผัส · สูตรเม็ดรวม FDC แล้ว\n";
    }

    public function down()
    {
        Schema::table('tb_monthly_doses', fn (Blueprint $t) => $t->dropColumn('dispensed_date'));
        Schema::table('tb_registrations', fn (Blueprint $t) => $t->dropColumn('no_household_contact'));
        DB::table('tb_dose_rules')->whereIn('drug_key', ['4FDC', '2FDC'])->delete();
        DB::table('tb_regimens')->whereIn('code', ['R08', 'R09'])->delete();
    }
};
