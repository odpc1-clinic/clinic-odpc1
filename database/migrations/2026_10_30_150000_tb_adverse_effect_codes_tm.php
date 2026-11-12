<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * รหัสผลข้างเคียงจากยาวัณโรค ใช้แบบ ICD-10-TM อย่างเดียว (คลินิกเลือก 2569-10-30)
 *
 * เดิมมีแค่ T36.6X5A/D/S (ICD-10-CM, Rifamycin ตัวเดียว) + Y41.1 — พยาบาลถามว่ายาตัวอื่นไม่มี
 * ระบบกลาง (43 แฟ้ม/สปสช.) รับเฉพาะ TM จึงปิดชุด CM ไม่ให้เลือกใหม่ (แถวที่เคยลงไว้ยังอยู่ อ่านได้ตามเดิม)
 * ชุด ICD-10-TM 2553 ที่นำเข้าไม่มีหมวด V–Y เลยต้องเพิ่มรหัส Y เข้ารายการของหมอเอง
 *
 * กลุ่ม Y40/Y41 ตาม ICD-10 WHO: Y41.1 ยาต้านวัณโรค "ไม่รวม" Rifamycin (Y40.6) และ Aminoglycoside (Y40.5)
 * Fluoroquinolone (Levofloxacin/Moxifloxacin) ยังรอหมอยืนยันกลุ่ม — ยังไม่ใส่ชื่อยาไว้ในรหัสไหน
 */
return new class extends Migration
{
    private const ROWS = [
        ['Y40.6', 'Rifamycins causing adverse effects in therapeutic use',
         'ผลไม่พึงประสงค์จากยากลุ่ม Rifamycin (Rifampicin, Rifapentine, 3HP) ที่ใช้ตามขนาดรักษา — รหัสรอง (ICD-10-TM)', 315],
        ['Y40.5', 'Aminoglycosides causing adverse effects in therapeutic use',
         'ผลไม่พึงประสงค์จากยากลุ่ม Aminoglycoside (Amikacin, Streptomycin) ที่ใช้ตามขนาดรักษา — รหัสรอง (ICD-10-TM)', 316],
        ['Y41.1', 'Antimycobacterial drugs causing adverse effects in therapeutic use',
         'ผลไม่พึงประสงค์จากยาต้านวัณโรค (Isoniazid/INH, Pyrazinamide/PZA, Ethambutol/EMB, Ethionamide, PAS, Clofazimine, Bedaquiline, Delamanid, Pretomanid) — ไม่รวม Rifampicin/Rifapentine (Y40.6) และ Amikacin (Y40.5) · รหัสรอง (ICD-10-TM)', 320],
        ['Y40.8', 'Other systemic antibiotics causing adverse effects in therapeutic use',
         'ผลไม่พึงประสงค์จากยาปฏิชีวนะอื่น (Linezolid, Cycloserine) ที่ใช้ตามขนาดรักษา — รหัสรอง (ICD-10-TM)', 325],
    ];

    private const CM = ['T36.6X5A', 'T36.6X5D', 'T36.6X5S'];

    public function up(): void
    {
        $now = now();
        foreach (self::ROWS as [$code, $en, $th, $sort]) {
            $exists = DB::table('icd10_codes')->where('code', $code)->exists();
            $data = [
                'source' => 'clinic', 'description_en' => $en, 'description_th' => $th,
                'group_type' => 'Adverse Drug Reaction', 'is_secondary' => 1, 'sort' => $sort, 'active' => 1, 'updated_at' => $now,
            ];
            $exists
                ? DB::table('icd10_codes')->where('code', $code)->update($data)
                : DB::table('icd10_codes')->insert($data + ['code' => $code, 'created_at' => $now]);
        }
        DB::table('icd10_codes')->whereIn('code', self::CM)->update(['active' => 0, 'updated_at' => $now]);
    }

    public function down(): void
    {
        DB::table('icd10_codes')->whereIn('code', ['Y40.6', 'Y40.5', 'Y40.8'])->delete();
        DB::table('icd10_codes')->whereIn('code', self::CM)->update(['active' => 1]);
    }
};
