<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * แคตตาล็อกยาวัณโรค (นำเข้าจากไฟล์ของคลินิก)
 *
 * ไม่ใช่คลังยา — ตารางนี้ตอบว่า "สูตรกลุ่มนี้ใช้ยาอะไรบ้าง ขนาดเท่าไหร่"
 * การจ่ายยาจริงยังทำที่ห้องยาผ่านตาราง medicines เหมือนเดิม
 */
class TbDrug extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['groups' => 'array', 'active' => 'boolean'];

    /** DS = ไม่ดื้อยา · Resist = ดื้อยา · TPT = ยาป้องกันในผู้สัมผัส */
    public static $groupOption = [
        'DS'     => 'ไม่ดื้อยา (DS)',
        'Resist' => 'ดื้อยา (Resist)',
        'TPT'    => 'ยาป้องกันในผู้สัมผัส (TPT)',
    ];

    /** ยาของกลุ่มหนึ่ง เรียงตามลำดับในไฟล์ต้นฉบับ */
    public static function ofGroup(string $group)
    {
        return self::where('active', true)
            ->where('groups', 'like', '%"' . $group . '"%')
            ->orderBy('sort')->get();
    }

    /** ชื่อพร้อมขนาด ไว้แสดงในรายการ */
    public function label(): string
    {
        return $this->name . ($this->dose ? ' (' . $this->dose . ')' : '');
    }

    /**
     * รหัสสั้นของยา — ต้องตรงกับรหัสที่ใช้เป็นช่องยาในตารางขนาดยาและในเกณฑ์ขนาดยา
     *
     * ยาทั่วไปเก็บรหัสไว้ในวงเล็บ เช่น "Rifampicin (R)" -> R
     * ยาเม็ดรวมในวงเล็บเป็นชื่อการค้า เช่น "4-FDC (Rimstar) I75-..." -> 4FDC
     */
    public function shortKey(): string
    {
        if (preg_match('/\(([^)]+)\)/', (string) $this->name, $m)
            && ! preg_match('/rimstar|rimactazid|rifanah/i', $m[1])) {
            $key = rtrim(trim($m[1]), '.');

            // เกณฑ์ขนาดยาและช่องยาในตารางใช้ H ไม่ใช่ INH — ให้เป็นตัวเดียวกัน
            return strcasecmp($key, 'INH') === 0 ? 'H' : $key;
        }

        $first = preg_split('/\s+/', trim((string) $this->name))[0] ?? '';

        return preg_replace('/[^A-Za-z0-9]/', '', $first);
    }

    /**
     * ยาทั้งหมดจัดกลุ่มไว้ให้เลือกตอนบันทึกสูตรยา
     *
     * คืนค่า: ['DS' => ['H' => ['label' => ..., 'strengths' => [...]], ...], 'Resist' => [...], 'TPT' => [...]]
     * ยาตัวเดียวกันที่มีหลายความแรงถูกยุบเป็นรายการเดียว แล้วเก็บความแรงไว้ในคำอธิบาย (2569-09-28)
     */
    public static function groupedForPicker(): array
    {
        $out = array_fill_keys(array_keys(self::$groupOption), []);

        foreach (self::where('active', true)->orderBy('sort')->get() as $drug) {
            $key = $drug->shortKey();
            if ($key === '') { continue; }

            foreach ((array) $drug->groups as $group) {
                if (! isset($out[$group])) { continue; }

                if (! isset($out[$group][$key])) {
                    $out[$group][$key] = ['label' => $drug->name, 'strengths' => [], 'note' => $drug->note];
                }
                if ($drug->dose) { $out[$group][$key]['strengths'][] = $drug->dose; }
            }
        }

        // รหัสยาที่มีเฉพาะในเกณฑ์ขนาดยา เช่น H-3HP / P-1HP (ยาตัวเดียวกันแต่คนละสูตร คนละขนาด)
        // ถ้าไม่เอามาแสดงด้วย พอเลือกสูตร 3HP จากรายการ ช่องติ๊กจะไม่มีให้ ทำให้ช่องยาในตารางหาย (2569-09-29)
        foreach (TbDoseRule::where('active', true)->orderBy('sort')->get() as $rule) {
            $group = $rule->group;
            if (! isset($out[$group]) || isset($out[$group][$rule->drug_key])) { continue; }

            $out[$group][$rule->drug_key] = [
                'label'     => $rule->drug_label ?: $rule->drug_key,
                'strengths' => $rule->strength ? [$rule->strength] : [],
                'note'      => $rule->note,
            ];
        }

        // ยาที่ไม่ใช้ในกลุ่มนั้นจริง แม้ไฟล์ต้นฉบับ/เกณฑ์ขนาดยาจะผูกไว้ (คลินิกแจ้ง 2569-09-29)
        // ตัดตอนแสดง ไม่แก้ในตาราง เพราะแคตตาล็อกนำเข้าจากไฟล์ของคลินิก นำเข้าใหม่เมื่อไรจะกลับมาอีก
        foreach (self::$notInGroup as $group => $keys) {
            foreach ($keys as $k) { unset($out[$group][$k]); }
        }

        // เรียงยาหลัก H R Z E ขึ้นก่อนเสมอ ตามลำดับที่หมอเขียนสูตรยา (2HRZE/4HR)
        // ที่เหลือคงลำดับเดิมจากไฟล์ต้นฉบับ
        foreach ($out as $group => $drugs) {
            $keys = array_keys($drugs);
            $rank = array_flip(self::$drugOrder);
            usort($keys, function ($a, $b) use ($rank, $keys) {
                $ra = $rank[$a] ?? 100 + array_search($a, $keys, true);
                $rb = $rank[$b] ?? 100 + array_search($b, $keys, true);
                return $ra <=> $rb;
            });
            $out[$group] = array_combine($keys, array_map(fn ($k) => $drugs[$k], $keys));
        }

        return $out;
    }

    /** ลำดับยาหลักที่ต้องขึ้นก่อนในทุกกลุ่ม */
    public static $drugOrder = ['H', 'R', 'Z', 'E'];

    /** ยาที่ไม่ต้องแสดงในกลุ่มนั้น — TPT ไม่ใช้ B6 (คลินิกแจ้ง 2569-09-29) */
    public static $notInGroup = ['TPT' => ['B6']];
}
