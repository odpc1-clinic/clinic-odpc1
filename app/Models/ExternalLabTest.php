<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** แคตตาล็อกรายการตรวจของแล็บภายนอก (ตามใบเสนอราคา) */
class ExternalLabTest extends Model
{
    protected $table = 'external_lab_tests';

    protected $fillable = ['vendor', 'code', 'name', 'specimen', 'turnaround', 'price', 'active'];

    protected $casts = ['active' => 'boolean', 'price' => 'decimal:2'];

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /** ข้อความสำหรับ dropdown: รหัส + ชื่อ + ราคาอ้างอิง */
    public function label(): string
    {
        // แล็บอื่นที่ไม่ใช่ N Health (เช่น แล็บวัณโรค 2569-09-30) บอกชื่อแล็บนำหน้า จะได้ไม่หยิบผิดที่ส่ง
        $vendor = trim((string) $this->vendor);
        return ($vendor !== '' && $vendor !== 'N Health' ? '[' . $vendor . '] ' : '')
            . $this->code . ' · ' . $this->name . ($this->price ? ' (' . number_format($this->price, 0) . '.-)' : '');
    }

    // ===================== กันสั่งผิด (2026-08-03) =====================
    // พยาบาลแจ้งว่าเคยสั่งผิดตัวจนคลินิกต้องจ่ายค่าแล็บนอกเอง
    // ต้นเหตุ 2 อย่าง: (1) ส่งนอกทั้งที่ห้องแล็บเราตรวจได้ (2) ชื่อคล้ายกันจนหยิบผิด

    /**
     * รายการที่ "ห้องแล็บเราตรวจเองได้" — ส่งนอกคือจ่ายฟรี
     * key = code ในแคตตาล็อก N Health, value = ชื่อวิธีตรวจฝั่งเรา (LabSub::$methodOption)
     */
    public static $inHouseEquivalent = [
        'N620' => 'HBs Ag',
        'N625' => 'HBs Ab',
        'N660' => 'Anti-HCV',
        'N390' => 'TP syphilis',
    ];

    /**
     * คู่ที่ชื่อคล้ายกันจนสั่งสลับกันได้ — เตือนให้ดูซ้ำก่อนบันทึก
     * ที่อันตรายสุดคือ HBsAg (ตัวเชื้อ) กับ HBsAb (ภูมิคุ้มกัน) ซึ่งความหมายตรงข้ามกันเลย
     */
    public static $confusable = [
        'N620' => ['code' => 'N625', 'note' => 'Antigen = ตัวเชื้อ (กำลังติดเชื้อ) · Antibody = ภูมิคุ้มกัน (เคยติด/ฉีดวัคซีนแล้ว)'],
        'N625' => ['code' => 'N620', 'note' => 'Antibody = ภูมิคุ้มกัน (เคยติด/ฉีดวัคซีนแล้ว) · Antigen = ตัวเชื้อ (กำลังติดเชื้อ)'],
        'N630' => ['code' => 'N635', 'note' => 'Core Antibody (รวม) ต่างจาก Core IgM (ติดเชื้อระยะเฉียบพลัน)'],
        'N635' => ['code' => 'N630', 'note' => 'Core IgM (ระยะเฉียบพลัน) ต่างจาก Core Antibody (รวม)'],
        'N005' => ['code' => 'N025', 'note' => 'T3 ต่างจาก Free T3'],
        'N025' => ['code' => 'N005', 'note' => 'Free T3 ต่างจาก T3'],
    ];

    /** ห้องแล็บเราตรวจรายการนี้เองได้ไหม — คืนชื่อวิธีตรวจฝั่งเรา หรือ null */
    public function inHouseName(): ?string
    {
        return self::$inHouseEquivalent[$this->code] ?? null;
    }

    /** คำเตือนคู่ที่สับสน — คืน ['name' => ชื่อรายการที่คล้าย, 'note' => คำอธิบาย] หรือ null */
    public function confusableWith(): ?array
    {
        $c = self::$confusable[$this->code] ?? null;
        if (!$c) { return null; }

        $other = static::where('code', $c['code'])->first();
        return ['name' => $other->name ?? $c['code'], 'note' => $c['note']];
    }
}
