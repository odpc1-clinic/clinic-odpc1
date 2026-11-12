<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * นำเข้า ICD-10-TM ภาษาไทยชุดเต็ม (พยาบาลขอ 2569-10-30)
 *
 * ต้นฉบับ: ICD-10.pdf ของ สนย. (2553) — แยกเป็น database/data/icd10_tm_2553.tsv แล้ว
 * (ไฟล์ PDF ใช้ฟอนต์ไทยรุ่นเก่า สระ/วรรณยุกต์เป็นอักขระ PUA แปลงกลับแล้ว และรวม ํ+า เป็น ำ ให้ค้น "ทำ" เจอ)
 *
 * รหัสที่หมอคัดไว้ (source=clinic) ไม่แตะเลย — ชื่อไทยของหมอเขียนให้คนไข้/พยาบาลอ่านง่ายกว่า
 * รันซ้ำได้: แถว tm เดิมอัปเดตชื่อ แถวใหม่เพิ่ม ไม่ลบอะไร (รหัสที่ถูกใช้ไปแล้วต้องยังอยู่)
 */
class ImportIcd10Tm extends Command
{
    protected $signature = 'icd10:import-tm {--file= : TSV (code, description_en, description_th)} {--dry : นับอย่างเดียว ไม่เขียน}';

    protected $description = 'นำเข้า ICD-10-TM ภาษาไทยชุดเต็มลงพจนานุกรมรหัสโรค (ไม่ทับรหัสที่หมอคัดไว้)';

    public function handle(): int
    {
        $file = $this->option('file') ?: database_path('data/icd10_tm_2553.tsv');
        if (! is_file($file)) {
            $this->error("ไม่พบไฟล์ {$file}");
            return 1;
        }

        $clinic = DB::table('icd10_codes')->where('source', '!=', 'tm')->pluck('code')->flip();
        $tm     = DB::table('icd10_codes')->where('source', 'tm')->pluck('code')->flip();

        $fh = fopen($file, 'r');
        fgets($fh);                                   // หัวตาราง
        $new = []; $upd = []; $skip = 0;
        while (($ln = fgets($fh)) !== false) {
            $p = explode("\t", rtrim($ln, "\r\n"));
            if (count($p) < 3 || $p[0] === '') { continue; }
            $code = strtoupper(trim($p[0]));
            if (isset($clinic[$code])) { $skip++; continue; }
            $row = [
                'code'           => $code,
                'source'         => 'tm',
                'description_en' => mb_substr(trim($p[1]), 0, 255),
                'description_th' => mb_substr(trim($p[2]), 0, 255),
                'group_type'     => 'ICD-10-TM',
                'is_secondary'   => 0,
                'sort'           => 10000,
                'active'         => 1,
            ];
            isset($tm[$code]) ? $upd[] = $row : $new[] = $row;
        }
        fclose($fh);

        $this->info('เพิ่มใหม่ ' . count($new) . ' · อัปเดต ' . count($upd) . " · ข้าม (หมอคัดไว้แล้ว) {$skip}");
        if ($this->option('dry')) { return 0; }

        $now = now();
        DB::transaction(function () use ($new, $upd, $now) {
            foreach (array_chunk($new, 500) as $chunk) {
                DB::table('icd10_codes')->insert(array_map(fn ($r) => $r + ['created_at' => $now, 'updated_at' => $now], $chunk));
            }
            foreach ($upd as $r) {
                DB::table('icd10_codes')->where('code', $r['code'])->where('source', 'tm')
                    ->update(['description_en' => $r['description_en'], 'description_th' => $r['description_th'], 'updated_at' => $now]);
            }
        });

        $this->info('เสร็จ — ทั้งพจนานุกรมมี ' . DB::table('icd10_codes')->count() . ' รหัส');
        return 0;
    }
}
