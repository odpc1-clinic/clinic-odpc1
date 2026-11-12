<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * เกณฑ์ขนาดยาชุดที่เหลือจาก Calculation_4TB.xlsx (คลินิกขอให้ทำครบ 2569-09-23)
 *   - เด็ก (DS/ped)       จากชีต 2ตาราง_ขนาดยาDS_TB_Ped  — คิดตาม มก./กก. และมีเพดานต่อวัน
 *   - ยาดื้อยา (Resist)    จากชีต DOSE_2ndLine            — ตามช่วงน้ำหนัก 35-50 / >50 / <35
 *   - ยาป้องกัน (TPT)      จากชีต TPT                     — ผู้ใหญ่/เด็ก คนละอัตรา
 *
 * เพิ่มสองคอลัมน์:
 *   max_mg    = เพดานต่อวัน (เด็กคิด มก./กก. แล้วต้องไม่เกินขนาดผู้ใหญ่)
 *   dose_text = ขนาดที่ไม่ใช่ตัวเลขเดียว เช่น Bedaquiline "400 มก./วัน 2 สัปดาห์แรก แล้ว 200 มก. 3 วัน/สัปดาห์"
 */
class ExpandTbDoseRules extends Migration
{
    public function up()
    {
        Schema::table('tb_dose_rules', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_mg')->nullable()->after('dose_mg');
            $table->string('dose_text')->nullable()->after('max_mg');
        });

        $now  = now();
        $sort = 1000;

        $insert = function (array $r) use (&$sort, $now) {
            DB::table('tb_dose_rules')->insert(array_merge([
                'group'        => 'DS',
                'patient_type' => 'adult',
                'strength'     => null,
                'weight_min'   => null,
                'weight_max'   => null,
                'dose_mg'      => null,
                'max_mg'       => null,
                'dose_text'    => null,
                'mgkg_min'     => null,
                'mgkg_max'     => null,
                'note'         => null,
                'sort'         => ++$sort,
                'active'       => true,
                'created_at'   => $now,
                'updated_at'   => $now,
            ], $r));
        };

        /* ---------- เด็ก (DS) — คิดตาม มก./กก. ทุกน้ำหนัก แต่ห้ามเกินเพดาน ---------- */
        foreach ([
            ['H',   'H (Isoniazid)',        '100 mg',      7,  15, 300,  'ปรับตาม NAT2'],
            ['R',   'R (Rifampicin)',       '300, 450 mg', 10, 20, 600,  ''],
            ['Z',   'Z (Pyrazinamide)',     '500 mg',      30, 40, 2000, ''],
            ['E',   'E (Ethambutol)',       '400, 500 mg', 15, 25, 1200, ''],
            ['Hhd', 'H high-dose',          '100, 300 mg', 15, 20, 900,  'ใช้เมื่อแพทย์สั่ง high-dose'],
        ] as [$key, $label, $strength, $min, $max, $cap, $note]) {
            $insert([
                'patient_type' => 'ped', 'drug_key' => $key, 'drug_label' => $label, 'strength' => $strength,
                'mgkg_min' => $min, 'mgkg_max' => $max, 'max_mg' => $cap, 'note' => $note ?: null,
            ]);
        }

        /* ---------- ยาดื้อยา (Resist) ผู้ใหญ่ — ตามช่วงน้ำหนัก ---------- */
        // [drug_key, label, strength, mgkg_min, mgkg_max, [[min,max,dose]], note]
        foreach ([
            ['Lfx', 'Levofloxacin',  '250, 500 mg', 15, 15,  [[35, 50, 500], [50.1, null, 750]], ''],
            ['Mfx', 'Moxifloxacin',  '400, 500 mg', 7.5, 10, [[35, null, 400]], ''],
            ['Pto', 'Prothionamide', '250, 500 mg', 10, 20,  [[35, 50, 500], [50.1, null, 750]], ''],
            ['Cfz', 'Clofazimine',   '100 mg',      2,  5,   [[35, null, 100]], ''],
            ['Z',   'Z (Pyrazinamide)', '500 mg',   20, 30,  [[35, 50, 1000], [50.1, null, 1500]], ''],
        ] as [$key, $label, $strength, $kgMin, $kgMax, $bands, $note]) {
            foreach ($bands as [$wMin, $wMax, $dose]) {
                $insert([
                    'group' => 'Resist', 'drug_key' => $key, 'drug_label' => $label, 'strength' => $strength,
                    'weight_min' => $wMin, 'weight_max' => $wMax, 'dose_mg' => $dose,
                    'mgkg_min' => $kgMin, 'mgkg_max' => $kgMax, 'note' => $note ?: null,
                ]);
            }
        }

        // Bedaquiline ขนาดไม่ใช่ตัวเลขเดียว (สองช่วงเวลา) — เก็บเป็นข้อความ
        $insert([
            'group' => 'Resist', 'drug_key' => 'Bdq', 'drug_label' => 'Bedaquiline', 'strength' => '100 mg',
            'dose_text' => '400 มก./วัน 2 สัปดาห์แรก แล้ว 200 มก. 3 วัน/สัปดาห์ (จ-พ-ศ)',
            'note' => 'ให้ 6 เดือน · ติดตาม QTc',
        ]);

        /* ---------- ยาป้องกันในผู้สัมผัส (TPT) ---------- */
        foreach ([
            ['adult', 'H',  'H (Isoniazid)',  '100, 300 mg', 5,  5,  300, 'สูตร 6-9 INH'],
            ['ped',   'H',  'H (Isoniazid)',  '100 mg',      7,  15, 300, 'สูตร 6-9 INH ในเด็ก'],
            ['adult', 'R',  'R (Rifampicin)', '300, 450 mg', 10, 10, 600, 'สูตร 3-4R'],
            ['ped',   'R',  'R (Rifampicin)', '300 mg',      10, 20, 600, 'สูตร 3-4R ในเด็ก'],
        ] as [$type, $key, $label, $strength, $min, $max, $cap, $note]) {
            $insert([
                'group' => 'TPT', 'patient_type' => $type, 'drug_key' => $key, 'drug_label' => $label,
                'strength' => $strength, 'mgkg_min' => $min, 'mgkg_max' => $max, 'max_mg' => $cap, 'note' => $note,
            ]);
        }

        // B6 ขนาดคงที่ ไม่คิดตามน้ำหนัก
        $insert([
            'group' => 'TPT', 'drug_key' => 'B6', 'drug_label' => 'Pyridoxine (B6)', 'strength' => '50 mg',
            'dose_text' => '10–100 มก. วันละครั้ง', 'note' => 'กินคู่ INH เสมอ',
        ]);
    }

    public function down()
    {
        DB::table('tb_dose_rules')->whereIn('group', ['Resist', 'TPT'])->delete();
        DB::table('tb_dose_rules')->where('patient_type', 'ped')->delete();

        Schema::table('tb_dose_rules', function (Blueprint $table) {
            $table->dropColumn(['max_mg', 'dose_text']);
        });
    }
}
