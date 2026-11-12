<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * U83.2 เชื้อดื้อยากลุ่ม Quinolone — ใช้ระบุ Pre-XDR (MDR + ดื้อ Fluoroquinolone) ผู้ใช้สั่งเพิ่ม 2569-10-30
 *
 * คู่มือ 506/DDS ของกรมควบคุมโรคยังไม่มีรหัส Pre-XDR/XDR จำเพาะ (ใช้ U84.7 = ดื้อหลายขนาน)
 * หมอขอให้ระบุการดื้อ FQ เป็นรหัสด้วย -> ลงคู่กับ U84.7 · ชุด ICD-10-TM 2553 ที่นำเข้าไม่มีหมวด U จึงเพิ่มเข้ารายการของหมอ
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $data = [
            'source'         => 'clinic',
            'description_en' => 'Resistance to quinolones',
            'description_th' => 'เชื้อดื้อยากลุ่ม Fluoroquinolone (Levofloxacin, Moxifloxacin) — ใช้คู่ U84.7 กรณี Pre-XDR · รหัสรอง',
            'group_type'     => 'Drug Resistance (Secondary)',
            'is_secondary'   => 1,
            'sort'           => 245,
            'active'         => 1,
            'updated_at'     => $now,
        ];
        DB::table('icd10_codes')->where('code', 'U83.2')->exists()
            ? DB::table('icd10_codes')->where('code', 'U83.2')->update($data)
            : DB::table('icd10_codes')->insert($data + ['code' => 'U83.2', 'created_at' => $now]);
    }

    public function down(): void
    {
        DB::table('icd10_codes')->where('code', 'U83.2')->delete();
    }
};
