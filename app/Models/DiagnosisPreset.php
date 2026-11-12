<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * รายการ diagnosis สำเร็จรูปต่อคลินิก (ตอนนี้ใช้กับ TMC) — พิมพ์ชื่อใหม่ตอนบันทึกวินิจฉัย = เพิ่มเข้ารายการให้เอง
 * ตัวที่บันทึกลง diagnoses เป็นโรค "Other" (11) + ชื่อในช่อง other_disease เหมือนที่ TMC ลงมาตลอด รายงานเดิมจึงไม่กระทบ
 */
class DiagnosisPreset extends Model
{
    protected $guarded = ['id'];

    public const OTHER_DISEASE = 11;   // Diagnosi::$diseaseOption "Other"

    public static function namesFor(int $clinic): array
    {
        return static::where('clinic', $clinic)->where('active', true)
            ->orderBy('sort')->orderBy('name')->pluck('name')->all();
    }

    /** ชื่อที่ยังไม่มีในรายการ → เพิ่มต่อท้าย · มีอยู่แล้ว (ไม่สนตัวพิมพ์เล็ก/ใหญ่/ช่องว่าง) → คืนชื่อที่มีอยู่ ให้สะกดตรงกันทุกเคส */
    public static function remember(int $clinic, string $name): string
    {
        $name = mb_substr(trim(preg_replace('/\s+/u', ' ', $name)), 0, 150);
        if ($name === '') { return ''; }

        $norm = fn ($s) => mb_strtolower(preg_replace('/\s+/u', '', $s));
        $hit = static::where('clinic', $clinic)->get()->first(fn ($p) => $norm($p->name) === $norm($name));
        if ($hit) {
            if (! $hit->active) { $hit->update(['active' => true]); }
            return $hit->name;
        }

        static::create([
            'clinic' => $clinic, 'name' => $name,
            'sort' => (int) static::where('clinic', $clinic)->max('sort') + 10,
            'created_by' => auth()->id(),
        ]);
        return $name;
    }
}
