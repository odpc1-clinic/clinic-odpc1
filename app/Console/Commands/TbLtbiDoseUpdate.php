<?php

namespace App\Console\Commands;

use App\Models\TbDoseRule;
use App\Models\TbRegimen;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * ปรับเกณฑ์ขนาดยา + สูตรยา LTBI (TPT) ตามแผ่น "ยาและสูตรยารักษาการติดเชื้อวัณโรคระยะแฝง"
 * ที่คลินิก TB ส่งมา 2569-11-03 (อ้างอิง WHO consolidated guidelines Module 1: Prevention 2020)
 *
 * เดิมเกณฑ์ 3HP เป็นตาม CDC (P 300/450/600/750/900 ตามน้ำหนัก, H 15 มก./กก.) ไม่ตรงแผ่นของคลินิก
 * และ 4R / 3HR / 6-9H ไม่ได้ผูกรายการยา เลยเลือกในกล่องสั่งยาไม่ได้
 *
 * เกณฑ์อายุแยกตามสูตร (ไม่ใช่ 18 ปีเหมือนสูตรรักษา) อยู่ใน TbDoseRule::TPT_AGE_CUT
 *
 * รันซ้ำได้ — เขียนทับค่าเดิมทุกครั้ง · ไม่ใส่ --apply = แสดงสิ่งที่จะเปลี่ยนเฉยๆ
 */
class TbLtbiDoseUpdate extends Command
{
    protected $signature = 'tb:ltbi-dose-update {--apply : บันทึกจริง}';
    protected $description = 'ปรับเกณฑ์ขนาดยา/สูตรยา LTBI ตามแผ่นแนวทาง WHO 2020 ของคลินิก (2569-11-03)';

    private const SRC = 'ตามแผ่นแนวทาง LTBI ของคลินิก (WHO 2020)';

    public function handle(): int
    {
        // [patient_type, drug_key, label, strength, weight_min, weight_max, dose_mg, max_mg, dose_text, mgkg_min, mgkg_max, note]
        $rules = [
            // 6-9H / 3HR — ≥10 ปี 5 มก./กก. · <10 ปี 10 (7-15) มก./กก.
            ['adult', 'H', 'H (Isoniazid)', '100, 300 mg', null, null, null, 300, null, 5, 5, 'สูตร 6-9H / 3HR อายุ 10 ปีขึ้นไป: 5 มก./กก./วัน · ' . self::SRC],
            ['ped',   'H', 'H (Isoniazid)', '100 mg',      null, null, null, 300, null, 10, 15, 'สูตร 6-9H / 3HR อายุต่ำกว่า 10 ปี: 10 (7-15) มก./กก./วัน · ' . self::SRC],
            // 4R / 3HR — ≥10 ปี 10 มก./กก. · <10 ปี 15 (10-20) มก./กก.
            ['adult', 'R', 'R (Rifampicin)', '300, 450 mg', null, null, null, 600, null, 10, 10, 'สูตร 4R / 3HR อายุ 10 ปีขึ้นไป: 10 มก./กก./วัน · ' . self::SRC],
            ['ped',   'R', 'R (Rifampicin)', '300 mg',      null, null, null, 600, null, 15, 20, 'สูตร 4R / 3HR อายุต่ำกว่า 10 ปี: 15 (10-20) มก./กก./วัน · ' . self::SRC],
            // 3HP สัปดาห์ละครั้ง 12 ครั้ง — 14 ปีขึ้นไป ≥30 กก. = 900/900 · 2-14 ปี ตามน้ำหนัก
            ['adult', 'H-3HP', 'Isoniazid (สูตร 3HP สัปดาห์ละครั้ง)', '100, 300 mg', 30, null, 900, 900, null, null, null, 'อายุ 14 ปีขึ้นไป น้ำหนัก 30 กก.ขึ้นไป · ' . self::SRC],
            ['ped',   'H-3HP', 'Isoniazid (สูตร 3HP สัปดาห์ละครั้ง)', '100, 300 mg', 10, 15.99, 300, 900, null, null, null, 'อายุ 2-14 ปี · ' . self::SRC],
            ['ped',   'H-3HP', 'Isoniazid (สูตร 3HP สัปดาห์ละครั้ง)', '100, 300 mg', 16, 23.99, 500, 900, null, null, null, 'อายุ 2-14 ปี · ' . self::SRC],
            ['ped',   'H-3HP', 'Isoniazid (สูตร 3HP สัปดาห์ละครั้ง)', '100, 300 mg', 24, 30, 600, 900, null, null, null, 'อายุ 2-14 ปี · ' . self::SRC],
            ['ped',   'H-3HP', 'Isoniazid (สูตร 3HP สัปดาห์ละครั้ง)', '100, 300 mg', 30.01, null, 700, 900, null, null, null, 'อายุ 2-14 ปี · ' . self::SRC],
            ['adult', 'P', 'Rifapentine (สูตร 3HP สัปดาห์ละครั้ง)', '150 mg', 30, null, 900, null, null, null, null, 'อายุ 14 ปีขึ้นไป น้ำหนัก 30 กก.ขึ้นไป · ' . self::SRC],
            ['ped',   'P', 'Rifapentine (สูตร 3HP สัปดาห์ละครั้ง)', '150 mg', 10, 15.99, 300, null, null, null, null, 'อายุ 2-14 ปี · ' . self::SRC],
            ['ped',   'P', 'Rifapentine (สูตร 3HP สัปดาห์ละครั้ง)', '150 mg', 16, 23.99, 450, null, null, null, null, 'อายุ 2-14 ปี · ' . self::SRC],
            ['ped',   'P', 'Rifapentine (สูตร 3HP สัปดาห์ละครั้ง)', '150 mg', 24, 30, 600, null, null, null, null, 'อายุ 2-14 ปี · ' . self::SRC],
            ['ped',   'P', 'Rifapentine (สูตร 3HP สัปดาห์ละครั้ง)', '150 mg', 30.01, null, 750, null, null, null, null, 'อายุ 2-14 ปี · ' . self::SRC],
            // เม็ดรวม 3HP (H300 + P300) — ผู้ใหญ่ ≥30 กก. 3 เม็ด = 900/900 · เด็กใช้ยาแยกตัว เพราะขนาดเด็กแบ่งจากเม็ดรวมไม่ลงตัว (คลินิกยืนยัน 2569-11-03)
            ['adult', 'HP-FDC', '3HP เม็ดรวม Rifapentine 300 + Isoniazid 300', 'P300/H300', 30, null, null, null, '3 เม็ด/ครั้ง', null, null, 'สัปดาห์ละครั้ง 12 ครั้ง · อายุ 14 ปีขึ้นไป น้ำหนัก 30 กก.ขึ้นไป (= H 900 + P 900) · ' . self::SRC],
            // 1HP ทุกวัน 28 วัน — อายุ 13 ปีขึ้นไป H 300 + P 600 ขนาดคงที่ (ต่ำกว่า 13 ปีไม่มีเกณฑ์)
            ['adult', 'H-1HP', 'Isoniazid (สูตร 1HP ทุกวัน)', '100, 300 mg', null, null, 300, null, null, null, null, 'อายุ 13 ปีขึ้นไป ขนาดคงที่ 300 มก./วัน 28 วัน · ' . self::SRC],
            ['adult', 'P-1HP', 'Rifapentine (สูตร 1HP ทุกวัน)', '150 mg', null, null, 600, null, null, null, null, 'อายุ 13 ปีขึ้นไป ขนาดคงที่ 600 มก./วัน 28 วัน คู่กับ INH 300 มก. · ' . self::SRC],
        ];

        // สูตรใน "กล่องสั่งยา" — ต้องผูกรายการยาถึงจะเลือกได้ · B6 เติมเองตามกติกา TbRegistration::needsB6 (ยาที่มี H)
        $regimens = [
            22 => ['regimen' => '3HP', 'drugs' => ['P' => 1, 'H-3HP' => 1]],
            25 => ['regimen' => '3HP (FDC)', 'drugs' => ['HP-FDC' => 1]],
            23 => ['regimen' => '1HP', 'drugs' => ['H-1HP' => 1, 'B6' => 1, 'P-1HP' => 1]],   // มี B6 ตามเดิม (คลินิกยืนยัน 2569-11-03)
            11 => ['regimen' => '3HR', 'category' => 'ยาป้องกัน — รายวัน 3 เดือน', 'name' => 'TPT — 3HR (ทุกวัน 3 เดือน)', 'drugs' => ['H' => 1, 'R' => 1], 'sort' => 133],
            10 => ['regimen' => '4R',  'category' => 'ยาป้องกัน — รายวัน 4 เดือน', 'name' => 'TPT — 4R (ทุกวัน 4 เดือน)', 'drugs' => ['R' => 1], 'sort' => 134],
            7  => ['regimen' => '6-9H', 'category' => 'ยาป้องกัน — รายวัน 6-9 เดือน', 'name' => 'TPT — 6-9H (ทุกวัน 6-9 เดือน)', 'drugs' => ['H' => 1], 'sort' => 135],
        ];

        $apply = (bool) $this->option('apply');
        DB::beginTransaction();
        try {
            $keys = collect($rules)->map(fn ($r) => $r[0] . '|' . $r[1])->unique()->values()->all();
            $old = TbDoseRule::where('group', 'TPT')->get()
                ->filter(fn ($r) => in_array($r->patient_type . '|' . $r->drug_key, $keys, true));
            $this->line('== เกณฑ์ขนาดยาเดิม (กลุ่ม TPT ที่จะถูกแทน) ' . $old->count() . ' แถว');
            foreach ($old as $r) {
                $this->line(sprintf('  - %s %s %s-%s kg mg=%s max=%s txt=%s mgkg=%s', $r->patient_type, $r->drug_key,
                    $r->weight_min ?? '', $r->weight_max ?? '', $r->dose_mg ?? '', $r->max_mg ?? '', $r->dose_text ?? '', $r->mgkg_min ?? ''));
            }
            // ปิดแถวเดิมแทนการลบ — ย้อนกลับได้ และหน้า "เกณฑ์ขนาดยา" ยังเห็นค่าเก่า
            TbDoseRule::whereIn('id', $old->pluck('id'))->update(['active' => false]);

            $sort = 200;
            $this->line('== เกณฑ์ใหม่ ' . count($rules) . ' แถว');
            foreach ($rules as [$type, $key, $label, $str, $wmin, $wmax, $mg, $max, $txt, $kmin, $kmax, $note]) {
                TbDoseRule::create([
                    'group' => 'TPT', 'patient_type' => $type, 'drug_key' => $key, 'drug_label' => $label, 'strength' => $str,
                    'weight_min' => $wmin, 'weight_max' => $wmax, 'dose_mg' => $mg, 'max_mg' => $max, 'dose_text' => $txt,
                    'mgkg_min' => $kmin, 'mgkg_max' => $kmax, 'note' => $note, 'sort' => $sort++, 'active' => true,
                ]);
                $this->line(sprintf('  + %s %s %s-%s kg mg=%s max=%s txt=%s mgkg=%s', $type, $key, $wmin ?? '', $wmax ?? '', $mg ?? '', $max ?? '', $txt ?? '', $kmin ?? ''));
            }

            $this->line('== สูตรยา');
            foreach ($regimens as $id => $set) {
                $r = TbRegimen::find($id);
                if (! $r) { $this->warn("  ไม่พบสูตร id {$id} — ข้าม"); continue; }
                $before = $r->regimen . ' ' . json_encode($r->drugs);
                $r->fill($set + ['active' => true])->save();
                $this->line("  {$id}: {$before}  ->  {$r->regimen} " . json_encode($r->drugs));
            }

            if ($apply) {
                DB::commit();
                $this->info('บันทึกแล้ว');
            } else {
                DB::rollBack();
                $this->warn('ยังไม่ได้บันทึก (ดูอย่างเดียว) — สั่งใหม่พร้อม --apply เพื่อบันทึกจริง');
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        return self::SUCCESS;
    }
}
