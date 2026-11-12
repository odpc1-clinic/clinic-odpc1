<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * น้ำหนัก + ขนาดยา (TB 01 หน้า 2) — หนึ่งแถว = การสั่งยาหนึ่งครั้ง
 * ขนาดยาเก็บเป็นข้อความ เพราะบางช่องพยาบาลเขียน "1 เม็ด" หรือ "-"
 *
 * แถวมาตรฐาน: 0 · 2wk · 1 · 2 ... (เหมือนตารางเสมหะ) + แถวนอกรอบที่ตั้งชื่อเอง เช่น "วันที่ 2" (2569-10-01)
 * month_no = เดือนที่แถวนั้นอยู่ (ใช้กับสติกเกอร์/ตัวคำนวณ) · label = ป้ายของแถว · sort = ลำดับเวลา
 */
class TbMonthlyDose extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected $casts = ['doses' => 'array'];

    /** ชื่อยาที่ใช้เป็นหัวตารางเมื่อบัตรยังไม่ได้ระบุสูตรยา (ชุดมาตรฐาน DS) */
    public static $defaultDrugs = ['H', 'R', 'Z', 'E'];

    public function registration()
    {
        return $this->belongsTo(TbRegistration::class, 'tb_registration_id');
    }

    /** ทุกทางที่แก้รอบ (กล่องสั่งยา / ใบสรุป / ลบ) จัด sort ตามช่วงนับรอบให้ — ดู TbRegistration::resortDoses */
    protected static function booted(): void
    {
        $fix = function (self $r) { optional(TbRegistration::find($r->tb_registration_id))->resortDoses(); };
        static::saved($fix);
        static::deleted($fix);
    }

    /** ป้ายของแถวมาตรฐานในตาราง — "2wk" อ่านว่า 2 สัปดาห์ (ตรงกับตารางเสมหะ) */
    public static function standardLabels(int $maxMonth): array
    {
        $out = ['0', '2wk'];
        for ($m = 1; $m <= $maxMonth; $m++) { $out[] = (string) $m; }
        return $out;
    }

    /** ป้ายที่คนอ่าน: "0" → เดือนที่ 0 · "2wk" → 2 สัปดาห์ · อื่น ๆ ตามที่พิมพ์ */
    public static function labelText(?string $label, ?int $monthNo = null): string
    {
        $label = trim((string) $label);
        if ($label === '') { $label = (string) (int) $monthNo; }
        if (preg_match('/^\d+$/', $label)) { return 'เดือนที่ ' . (int) $label; }
        if (preg_match('/^(\d+)\s*wk$/i', $label, $m)) { return $m[1] . ' สัปดาห์'; }
        return $label;
    }

    /**
     * ตำแหน่งตามเวลา (หน่วยเป็นเดือน) ใช้เรียงแถว: "2wk" อยู่ระหว่างเดือน 0 กับ 1
     * แถวนอกรอบอ่านจากป้ายถ้าบอกเวลาไว้ ("วันที่ 2", "6wk", "3 สัปดาห์") ไม่งั้นไปต่อท้ายเดือนของมัน
     */
    public static function position(?string $label, int $monthNo): float
    {
        $label = trim((string) $label);
        if ($label === '' || preg_match('/^\d+$/', $label)) { return $label === '' ? $monthNo : (float) $label; }
        if (preg_match('/^(\d+)\s*(?:wk|สัปดาห์)$/iu', $label, $m)) { return ((float) $m[1]) / 4.345; }
        if (preg_match('/(?:วันที่|day|d)\s*(\d+)/iu', $label, $m)) { return max($monthNo, ((float) $m[1]) / 30.44); }
        return $monthNo + 0.5;
    }

    /** ค่า sort ที่เก็บในฐาน = ตำแหน่งตามเวลา × 100 */
    public static function sortValue(?string $label, int $monthNo): int
    {
        return (int) round(self::position($label, $monthNo) * 100);
    }

    public function labelForHumans(): string
    {
        return self::labelText($this->label, (int) $this->month_no);
    }

    public function auditLabel(): string
    {
        return 'ขนาดยา ' . $this->labelForHumans();
    }
}
