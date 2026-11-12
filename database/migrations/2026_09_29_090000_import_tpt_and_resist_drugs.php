<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * เติมยาและสูตรที่ระบบยังขาด — ยาป้องกัน (TPT) และยาดื้อยา
 *
 * ที่มาของตัวเลข: CDC Treatment of Latent TB Infection และ WHO consolidated guidelines (BPaLM)
 * คลินิกอนุมัติให้ใส่เมื่อ 2569-09-29 โดยให้เภสัช/พยาบาลตรวจทานอีกครั้งที่หน้าตั้งค่าเกณฑ์ขนาดยา
 *
 * หมายเหตุเรื่องรหัสยา: ยาตัวเดียวกันที่ขนาดต่างกันตามสูตร ต้องแยกรหัส
 * เพราะเกณฑ์ขนาดยาผูกกับ "รหัสยา" ไม่ได้ผูกกับสูตร
 *   P      = Rifapentine สูตร 3HP (สัปดาห์ละครั้ง ตามน้ำหนัก)
 *   P-1HP  = Rifapentine สูตร 1HP (600 มก. ทุกวัน)
 *   H-3HP  = Isoniazid สูตร 3HP (15 มก./กก. สัปดาห์ละครั้ง) ต่างจาก H ของสูตรกินทุกวัน
 */
return new class extends Migration
{
    public function up()
    {
        $now = now();

        /* ---------- 1) ยาที่ยังไม่มีในรายการ ---------- */
        $drugs = [
            ['name' => 'Rifapentine (P)',        'dose' => '150 mg',   'groups' => ['TPT'],    'note' => 'ใช้ในสูตร 3HP และ 1HP', 'sort' => 120],
            ['name' => 'Delamanid (Dlm)',        'dose' => '50 mg',    'groups' => ['Resist'], 'note' => null, 'sort' => 121],
            ['name' => 'Ethionamide (Eto)',      'dose' => '250 mg',   'groups' => ['Resist'], 'note' => 'กลุ่มเดียวกับ Prothionamide', 'sort' => 122],
            ['name' => 'Amikacin (Am)',          'dose' => '500 mg/2 ml', 'groups' => ['Resist'], 'note' => 'ยาฉีด — ต้องเฝ้าระวังการได้ยิน', 'sort' => 123],
            ['name' => 'Kanamycin (Km)',         'dose' => '1 g',      'groups' => ['Resist'], 'note' => 'ยาฉีด — สูตรเดิม ปัจจุบันใช้น้อยลง', 'sort' => 124],
            ['name' => 'PAS',                    'dose' => '4 g',      'groups' => ['Resist'], 'note' => 'ผงละลายน้ำ', 'sort' => 125],
            ['name' => 'Isoniazid high-dose (Hhd)', 'dose' => '300 mg', 'groups' => ['Resist'], 'note' => 'ใช้เมื่อแพทย์สั่ง high-dose', 'sort' => 126],
        ];
        foreach ($drugs as $d) {
            if (DB::table('tb_drugs')->where('name', $d['name'])->exists()) { continue; }
            DB::table('tb_drugs')->insert([
                'name' => $d['name'], 'dose' => $d['dose'], 'groups' => json_encode($d['groups']),
                'note' => $d['note'], 'sort' => $d['sort'], 'active' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        /* ---------- 2) สูตรยาป้องกันที่ยังขาด ---------- */
        $regimens = [
            ['code' => 'R11', 'name' => 'TPT · 3HP (สัปดาห์ละครั้ง 12 ครั้ง)',
             'category' => 'ยาป้องกัน · รายสัปดาห์', 'regimen' => '3HP',
             'drugs' => ['H-3HP' => 1, 'P' => 1, 'B6' => 1], 'sort' => 130],
            ['code' => 'R12', 'name' => 'TPT · 1HP (ทุกวัน 28 วัน)',
             'category' => 'ยาป้องกัน · รายวัน 1 เดือน', 'regimen' => '1HP',
             'drugs' => ['H' => 1, 'P-1HP' => 1, 'B6' => 1], 'sort' => 131],
            ['code' => 'R13', 'name' => 'TPT · 6Lfx (ผู้สัมผัสผู้ป่วยดื้อยา)',
             'category' => 'ยาป้องกัน · ผู้สัมผัส MDR', 'regimen' => '6Lfx',
             'drugs' => ['Lfx' => 1], 'sort' => 132],
        ];
        foreach ($regimens as $r) {
            if (DB::table('tb_regimens')->where('code', $r['code'])->exists()) { continue; }
            DB::table('tb_regimens')->insert([
                'code' => $r['code'], 'name' => $r['name'], 'category' => $r['category'],
                'regimen' => $r['regimen'], 'drugs' => json_encode($r['drugs']),
                'sort' => $r['sort'], 'active' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        /* ---------- 3) เกณฑ์ขนาดยา ---------- */
        $src = 'อ้างอิง CDC/WHO — ตรวจทานกับแนวทางของคลินิกก่อนใช้';
        $rule = function (array $r) use ($now, $src) {
            DB::table('tb_dose_rules')->insert(array_merge([
                'group' => 'TPT', 'patient_type' => 'adult', 'strength' => null,
                'weight_min' => null, 'weight_max' => null, 'dose_mg' => null, 'max_mg' => null,
                'dose_text' => null, 'mgkg_min' => null, 'mgkg_max' => null,
                'note' => $src, 'active' => 1, 'created_at' => $now, 'updated_at' => $now,
            ], $r));
        };

        // Rifapentine ในสูตร 3HP — ตามช่วงน้ำหนัก (สัปดาห์ละครั้ง)
        if (! DB::table('tb_dose_rules')->where('drug_key', 'P')->exists()) {
            $sort = 1040;
            foreach ([[10, 14, 300], [14.1, 25, 450], [25.1, 32, 600], [32.1, 49.9, 750], [50, null, 900]] as [$min, $max, $mg]) {
                $rule([
                    'drug_key' => 'P', 'drug_label' => 'Rifapentine (สูตร 3HP สัปดาห์ละครั้ง)', 'strength' => '150 mg',
                    'weight_min' => $min, 'weight_max' => $max, 'dose_mg' => $mg,
                    'note' => 'กินสัปดาห์ละครั้ง 12 ครั้ง · ' . $src, 'sort' => $sort++,
                ]);
            }
        }

        // Isoniazid ในสูตร 3HP — 15 มก./กก. ปัดขึ้น สูงสุด 900 (ต่างจาก H ของสูตรกินทุกวัน)
        if (! DB::table('tb_dose_rules')->where('drug_key', 'H-3HP')->exists()) {
            $rule([
                'drug_key' => 'H-3HP', 'drug_label' => 'Isoniazid (สูตร 3HP สัปดาห์ละครั้ง)', 'strength' => '100, 300 mg',
                'mgkg_min' => 15, 'mgkg_max' => 15, 'max_mg' => 900,
                'note' => 'กินสัปดาห์ละครั้งพร้อม Rifapentine · ' . $src, 'sort' => 1045,
            ]);
        }

        // Rifapentine ในสูตร 1HP — 600 มก. ทุกวัน 28 วัน
        if (! DB::table('tb_dose_rules')->where('drug_key', 'P-1HP')->exists()) {
            $rule([
                'drug_key' => 'P-1HP', 'drug_label' => 'Rifapentine (สูตร 1HP ทุกวัน)', 'strength' => '150 mg',
                'dose_mg' => 600, 'note' => 'กินทุกวัน 28 วัน คู่กับ INH 300 มก. · ' . $src, 'sort' => 1046,
            ]);
        }

        // Levofloxacin สำหรับผู้สัมผัสผู้ป่วยดื้อยา (6Lfx) — กลุ่ม TPT ใช้เกณฑ์เดียวกับผู้ใหญ่ทั่วไป
        if (! DB::table('tb_dose_rules')->where('group', 'TPT')->where('drug_key', 'Lfx')->exists()) {
            $sort = 1047;
            foreach ([[35, 50, 500], [50.1, null, 750]] as [$min, $max, $mg]) {
                $rule([
                    'drug_key' => 'Lfx', 'drug_label' => 'Levofloxacin (สูตร 6Lfx)', 'strength' => '250, 500 mg',
                    'weight_min' => $min, 'weight_max' => $max, 'dose_mg' => $mg,
                    'note' => 'ผู้สัมผัสผู้ป่วยดื้อยา กินทุกวัน 6 เดือน · ' . $src, 'sort' => $sort++,
                ]);
            }
        }

        // กลุ่มดื้อยา: Pretomanid · Linezolid · Delamanid (ที่ค้างมาตั้งแต่ 24/9)
        foreach ([
            ['drug_key' => 'Pa', 'drug_label' => 'Pretomanid', 'strength' => '200 mg', 'dose_mg' => 200,
             'note' => '200 มก./วัน ตลอด 24 สัปดาห์ (สูตร BPaLM/BPaL) · ' . $src, 'sort' => 1050],
            ['drug_key' => 'Lzd', 'drug_label' => 'Linezolid', 'strength' => '600 mg', 'dose_mg' => 600,
             'note' => '600 มก./วัน 16 สัปดาห์แรก แล้วลดเหลือ 300 มก./วัน อีก 8 สัปดาห์ · ' . $src, 'sort' => 1051],
            ['drug_key' => 'Dlm', 'drug_label' => 'Delamanid', 'strength' => '50 mg',
             'dose_text' => '100 มก. วันละ 2 ครั้ง (24 สัปดาห์)',
             'note' => 'ใช้เมื่อแพทย์สั่ง · ' . $src, 'sort' => 1052],
        ] as $r) {
            if (DB::table('tb_dose_rules')->where('group', 'Resist')->where('drug_key', $r['drug_key'])->exists()) { continue; }
            $rule(array_merge(['group' => 'Resist'], $r));
        }

        echo "  เติมยา " . count($drugs) . " ตัว · สูตร " . count($regimens) . " สูตร · เกณฑ์ขนาดยาของ P, H-3HP, P-1HP, Lfx(TPT), Pa, Lzd, Dlm แล้ว\n";
    }

    public function down()
    {
        DB::table('tb_dose_rules')->whereIn('drug_key', ['P', 'P-1HP', 'H-3HP', 'Pa', 'Lzd', 'Dlm'])->delete();
        DB::table('tb_dose_rules')->where('group', 'TPT')->where('drug_key', 'Lfx')->delete();
        DB::table('tb_regimens')->whereIn('code', ['R11', 'R12', 'R13'])->delete();
        DB::table('tb_drugs')->whereIn('name', [
            'Rifapentine (P)', 'Delamanid (Dlm)', 'Ethionamide (Eto)', 'Amikacin (Am)',
            'Kanamycin (Km)', 'PAS', 'Isoniazid high-dose (Hhd)',
        ])->delete();
    }
};
