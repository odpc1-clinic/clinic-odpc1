<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ผลข้างเคียงจาก Fluoroquinolone (Levofloxacin, Moxifloxacin) ลง Y40.8 (ผู้ใช้เลือก 2569-10-30)
 *
 * ก้ำกึ่งระหว่าง Y40.8 (ยาปฏิชีวนะอื่น) กับ Y41.8 (ยาต้านเชื้ออื่น) — เลือก Y40.8 ตาม ICD-10-CM ที่จัด FQ ไว้ T36.8
 * หาเล่มดัชนียา ICD-10-TM มายืนยันไม่ได้ · ใส่ชื่อยาไว้ในคำอธิบายเพื่อให้พิมพ์ชื่อยาแล้วเจอรหัส
 */
return new class extends Migration
{
    private const NEW = 'ผลไม่พึงประสงค์จากยาปฏิชีวนะอื่น (Linezolid, Cycloserine, Levofloxacin, Moxifloxacin) ที่ใช้ตามขนาดรักษา — รหัสรอง (ICD-10-TM)';
    private const OLD = 'ผลไม่พึงประสงค์จากยาปฏิชีวนะอื่น (Linezolid, Cycloserine) ที่ใช้ตามขนาดรักษา — รหัสรอง (ICD-10-TM)';

    public function up(): void
    {
        DB::table('icd10_codes')->where('code', 'Y40.8')->update(['description_th' => self::NEW, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('icd10_codes')->where('code', 'Y40.8')->update(['description_th' => self::OLD]);
    }
};
