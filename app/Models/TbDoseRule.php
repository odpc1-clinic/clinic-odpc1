<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * เกณฑ์ขนาดยาตามน้ำหนัก — ใช้คำนวณขนาดยาแนะนำให้หมอ/พยาบาล
 *
 * ผลที่ได้เป็น "ค่าแนะนำ" เท่านั้น คนสั่งยายังแก้ตัวเลขได้เสมอ
 * (ของจริงมีเหตุให้ปรับ เช่น ตับ/ไตไม่ดี หรือผล NAT2 ของ INH)
 */
class TbDoseRule extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['active' => 'boolean'];

    /**
     * ยาป้องกัน (TPT) ตัดเด็ก/ผู้ใหญ่คนละอายุตามสูตร ไม่ใช่ 18 ปีแบบสูตรรักษา
     * ตามแผ่นแนวทาง LTBI ของคลินิก (WHO 2020) ที่ส่งมา 2569-11-03:
     *   3HP (H-3HP / P / เม็ดรวม) — อายุ 14 ปีขึ้นไปใช้ขนาดผู้ใหญ่ · 2-14 ปีใช้ตารางน้ำหนักเด็ก
     *   1HP (H-1HP / P-1HP)       — ใช้ได้ตั้งแต่ 13 ปี ต่ำกว่านั้นไม่มีเกณฑ์
     *   4R / 3HR / 6-9H (H / R)   — 10 ปีขึ้นไปคิด มก./กก. แบบผู้ใหญ่
     * อายุ >= ค่านี้ = ใช้แถว adult · ต่ำกว่า = แถว ped
     */
    public const TPT_AGE_CUT = ['H-3HP' => 14, 'P' => 14, 'HP-FDC' => 14, 'H-1HP' => 13, 'P-1HP' => 13, 'H' => 10, 'R' => 10];

    /**
     * คำนวณขนาดยาแนะนำจากน้ำหนัก
     *
     * @return array<int, array{drug_key:string, label:string, strength:?string, dose:?int,
     *                          source:string, range:?string, note:?string}>
     */
    public static function suggest(float $weight, string $group = 'DS', string $type = 'adult'): array
    {
        if ($weight <= 0) { return []; }

        $rules = self::where('active', true)
            ->where('group', $group)->where('patient_type', $type)
            ->orderBy('sort')->get()
            ->groupBy('drug_key');

        $out = [];
        foreach ($rules as $key => $rows) {
            $first = $rows->first();

            // 1) ช่วงน้ำหนักตรงไหม -> ใช้ขนาดมาตรฐาน
            $band = $rows->first(function ($r) use ($weight) {
                $minOk = $r->weight_min === null || $weight >= (float) $r->weight_min;
                $maxOk = $r->weight_max === null || $weight <= (float) $r->weight_max;
                return $minOk && $maxOk;
            });

            $capped = false;

            // ขนาดที่ไม่ใช่ตัวเลขเดียว เช่น Bedaquiline ที่เปลี่ยนขนาดตามสัปดาห์ หรือยาเม็ดรวมที่นับเป็นเม็ด
            // ต้องอ่านจากแถวของ "ช่วงน้ำหนักที่ตรง" ไม่ใช่แถวแรกเสมอ
            // ไม่งั้นยาเม็ดรวมจะแนะนำจำนวนเม็ดของช่วงแรกให้ทุกน้ำหนัก (เจอตอนใส่ FDC 2569-09-28)
            if ($band && $band->dose_text) {
                $out[] = [
                    'drug_key' => $key,
                    'label'    => $band->drug_label,
                    'strength' => $band->strength,
                    'dose'     => null,
                    'dose_text' => $band->dose_text,
                    'source'   => ($band->weight_min || $band->weight_max)
                        ? 'ตามช่วงน้ำหนัก'
                        : 'ขนาดคงที่ตามแนวทาง',
                    'range'    => null,
                    'capped'   => false,
                    'note'     => $band->note,
                ];
                continue;
            }

            if ($band && $band->dose_mg) {
                $dose   = (int) $band->dose_mg;
                $source = 'ขนาดมาตรฐานของช่วงน้ำหนัก';
            } elseif ($first->mgkg_min) {
                // 2) นอกช่วง หรือเกณฑ์ที่คิดตาม มก./กก. ล้วน (เด็ก/TPT) -> ปัดเป็นหลักสิบ
                $dose   = (int) (round($weight * (float) $first->mgkg_min / 10) * 10);
                $source = 'คิดจาก ' . rtrim(rtrim((string) $first->mgkg_min, '0'), '.') . ' มก./กก.';
            } else {
                continue;
            }

            // เพดานต่อวัน — เด็กคิดตามน้ำหนักแล้วต้องไม่เกินขนาดผู้ใหญ่
            if ($first->max_mg && $dose > (int) $first->max_mg) {
                $dose   = (int) $first->max_mg;
                $capped = true;
                $source = 'ถึงเพดาน ' . $first->max_mg . ' มก./วัน';
            }

            $range = $first->mgkg_min
                ? round($weight * (float) $first->mgkg_min) . '–' . round($weight * (float) $first->mgkg_max) . ' มก.'
                : null;

            $out[] = [
                'drug_key'  => $key,
                'label'     => $first->drug_label,
                'strength'  => $first->strength,
                'dose'      => $dose,
                'dose_text' => null,
                'source'    => $source,
                'range'     => $range,
                'capped'    => $capped,
                'note'      => $first->note,
            ];
        }

        return $out;
    }
}
