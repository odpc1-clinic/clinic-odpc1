<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * แคตตาล็อกแล็บรวม — "เมนู" ของรายการตรวจทุกตัวที่สั่งได้ (2569-10-02)
 *
 * ตารางนี้ไม่เก็บสิ่งที่สั่ง ของจริงยังลงที่เดิม (ดู App\Services\LabCatalogOrder)
 * เป้าหมายคือคลินิกใหม่ใช้ตัวเลือกเดียวกันได้เลย ไม่ต้องสร้างหน้าสั่งแล็บใหม่ทุกครั้ง
 */
class LabCatalogItem extends Model
{
    use Auditable;

    protected $table = 'lab_catalog_items';

    protected $fillable = [
        'name', 'code', 'category', 'kind', 'target',
        'lab_method', 'lab_specimen', 'external_lab_test_id', 'tb_key',
        'default_price', 'price_note', 'clinics', 'active', 'sort',
    ];

    protected $casts = [
        'clinics'       => 'array',
        'active'        => 'boolean',
        'default_price' => 'decimal:2',
        'lab_method'    => 'integer',
        'lab_specimen'  => 'integer',
    ];

    public const IN_HOUSE   = 'in_house';
    public const EXTERNAL   = 'external';
    public const TB_SENDOUT = 'tb_sendout';

    /**
     * หมวด — ลำดับตามนี้ทุกคลินิก (เขียนไว้ในโค้ด ไม่แยกตาราง: หมวดแทบไม่เปลี่ยน และชื่อหมวดต้องตรงกันทุกจอ)
     * จัดตาม "ตรวจอะไร" ไม่ใช่ "ตรวจที่ไหน" — HIV ในคลินิกกับ HBsAg ส่งนอกอยู่หมวดเดียวกัน แยกกันด้วยป้ายสีแทน
     */
    public const CATEGORIES = [
        'serology'   => 'HIV / ซิฟิลิส / ไวรัสตับ',
        'tb'         => 'วัณโรค',
        // EKG ไม่ใช่แล็บเลือด — หมวดของตัวเอง สั่งและลงผลในหน้าต่างเดียวกัน (คลินิกขอ 2569-10-22)
        'cardio'     => 'การตรวจวินิจฉัยทางหัวใจ (Cardiological investigation)',
        'sti_swab'   => 'ตรวจสิ่งคัดหลั่ง / เชื้อ STI',
        'hematology' => 'เลือด (CBC / การแข็งตัว)',
        'chemistry'  => 'เคมีคลินิก (ตับ ไต ไขมัน น้ำตาล เกลือแร่)',
        'hormone'    => 'ไทรอยด์ / ฮอร์โมน',
        'urine'      => 'ปัสสาวะ / อุจจาระ / สารน้ำ',
        'micro'      => 'เพาะเชื้อ / ย้อมเชื้อ',
        'immunity'   => 'ภูมิคุ้มกัน / ไวรัสอื่น',
        'cytology'   => 'Pap / HPV',
        'other'      => 'อื่น ๆ',
    ];

    public const KIND_LABELS = [
        self::IN_HOUSE   => 'ในคลินิก',
        self::EXTERNAL   => 'ส่งนอก',
        self::TB_SENDOUT => 'ส่งตรวจวัณโรค',
    ];

    /** รายการวัณโรคที่ต้องมีสิ่งส่งตรวจ (ข้อ 2 ของส่วนส่งตรวจ ผูกกับข้อ 1 — ดู tb/_lab_fields) */
    public const TB_NEEDS_SPECIMEN = ['afb', 'mol:xpert', 'mol:lpa', 'mol:pcr', 'culture_dst'];

    /** วิธีตรวจในคลินิกที่ต้องเลือกตำแหน่งสิ่งส่งตรวจ (เหมือนหน้าสั่ง Lab เต็ม) */
    public const INHOUSE_NEEDS_SPECIMEN = [1, 2, 3, 4, 5, 13];

    public static $clinicLabels = [1 => 'STI', 2 => 'TB', 3 => 'TMC'];

    /**
     * ตารางขึ้นแล้วหรือยัง — migration ต้องรอผู้ดูแลอนุมัติ ระหว่างนั้นทุกหน้าใช้ปุ่ม/ขั้นตอนเดิม
     * จำไว้ทั้ง request (หน้าตรวจเรียกหลายครั้ง)
     */
    public static function ready(): bool
    {
        return self::$ready ??= Schema::hasTable('lab_catalog_items');
    }

    protected static ?bool $ready = null;
    protected static array $availCache = [];

    /** ใช้ในเทสต์ — สร้าง/ลบตารางชั่วคราวแล้วต้องให้ ready() อ่านใหม่ */
    public static function resetReady(): void
    {
        self::$ready = null;
        self::$availCache = [];
    }

    /** คลินิกนี้มีรายการให้สั่งไหม — ไม่มีตาราง/ไม่มีรายการ = ใช้ปุ่มเดิม */
    public static function availableFor(int $clinic): bool
    {
        if (! self::ready()) { return false; }
        return self::$availCache[$clinic] ??= self::query()->forClinic($clinic)->exists();
    }

    public function scopeForClinic($q, int $clinic)
    {
        return $q->where('active', true)->where(function ($w) use ($clinic) {
            $w->whereNull('clinics')->orWhereJsonContains('clinics', $clinic);
        });
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function externalTest()
    {
        return $this->belongsTo(ExternalLabTest::class, 'external_lab_test_id');
    }

    /** ป้ายบอกว่าทำที่ไหน — แล็บนอกบอกชื่อแล็บด้วย จะได้ไม่หยิบผิดที่ส่ง */
    public function kindLabel(?ExternalLabTest $t = null): string
    {
        if ($this->kind === self::EXTERNAL) {
            $t = $t ?: $this->externalTest;
            $v = trim((string) ($t->vendor ?? ''));
            return 'ส่งนอก' . ($v !== '' ? ' (' . $v . ')' : '');
        }
        return self::KIND_LABELS[$this->kind] ?? $this->kind;
    }

    public function needsSpecimen(): bool
    {
        if ($this->kind === self::IN_HOUSE) { return in_array((int) $this->lab_method, self::INHOUSE_NEEDS_SPECIMEN, true); }
        if ($this->kind === self::TB_SENDOUT) { return in_array($this->tb_key, self::TB_NEEDS_SPECIMEN, true); }
        return false;
    }

    /** Other (method 10) ต้องพิมพ์ชื่อการตรวจเอง */
    public function needsText(): bool
    {
        return $this->kind === self::IN_HOUSE && (int) $this->lab_method === 10;
    }

    /** ตัวเลือกตำแหน่ง/สิ่งส่งตรวจ — ชุดเดียวกับหน้าสั่ง Lab เต็ม (PCR ใช้ชุดของ PCR) / ช่องส่งตรวจวัณโรค */
    public function specimenOptions(): array
    {
        if (! $this->needsSpecimen()) { return []; }
        if ($this->kind === self::TB_SENDOUT) { return TbScreening::$specimenOption; }
        $opts = (int) $this->lab_method === 5 ? LabSub::$specimenFromPCROption : LabSub::$specimenFromOption;
        unset($opts['']);
        return $opts;
    }

    /**
     * ราคาที่แสดงในตัวเลือก
     *
     * แล็บในคลินิก: อ่านสด ๆ จาก lab_prices ตามกติกาเดียวกับห้องยา/การเงิน (LabSub::billingCategory)
     *   — ไม่เก็บราคาซ้ำในแคตตาล็อก เพราะบิลคิดจาก lab_prices ถ้าเก็บสองที่จะเพี้ยนกันเมื่อใครแก้ที่เดียว
     *   — แก้ราคา/ยกเว้นตอนสั่งไม่ได้ (ระบบบิลของแล็บในคลินิกยังไม่รองรับ) ให้แก้ที่ห้องยา/การเงินเหมือนเดิม
     * แล็บนอก: ราคาที่เก็บคนไข้ = default_price ถ้าผู้ดูแลตั้งไว้ ไม่งั้นว่างให้พยาบาลกรอก (เหมือนเดิม — ไม่เติมราคาใบเสนอราคาให้เอง)
     * ส่งตรวจวัณโรค: ไม่มีค่าใช้จ่ายเข้าบิล (ส่วนนี้ไม่เคยคิดเงิน)
     *
     * คืน ['price' => float|null, 'note' => string, 'editable' => bool, 'ref' => float|null]
     */
    public function priceInfo(?array $labPrices = null, ?ExternalLabTest $t = null): array
    {
        if ($this->kind === self::IN_HOUSE) {
            $labPrices = $labPrices ?? LabPrice::pluck('price', 'name')->toArray();
            $name   = LabSub::$methodOption[$this->lab_method] ?? '';
            $master = isset($labPrices[$name]) ? (float) $labPrices[$name] : null;
            $cat    = LabSub::billingCategory($this->lab_method);
            if ($cat === 'blood') {
                return ['price' => $master, 'note' => 'ราคากลาง · คิดที่ห้องยา/การเงิน', 'editable' => false, 'ref' => $master];
            }
            if ($cat === 'sso') {
                return ['price' => $master, 'note' => 'เบิก สปสช. ได้ (ติ๊กเบิกที่ห้องยา = ไม่คิดเงิน)', 'editable' => false, 'ref' => $master];
            }
            return ['price' => null, 'note' => 'คลินิกคิดเอง/รวมแพ็กเกจ' . ($master ? ' (ราคากลาง ' . number_format($master, 0) . '.-)' : ''),
                    'editable' => false, 'ref' => $master];
        }
        if ($this->kind === self::EXTERNAL) {
            $t   = $t ?: $this->externalTest;
            $ref = $t && $t->price !== null ? (float) $t->price : null;
            $note = $this->price_note ?: ($ref !== null ? 'ใบเสนอราคา ' . number_format($ref, 0) . '.- (ราคาเก็บคนไข้กรอกเอง)' : 'ยังไม่มีราคา — กรอกเอง');
            return ['price' => $this->default_price !== null ? (float) $this->default_price : null,
                    'note' => $note, 'editable' => true, 'ref' => $ref];
        }
        return ['price' => null, 'note' => $this->price_note ?: 'ไม่คิดเงิน', 'editable' => false, 'ref' => null];
    }

    public function auditLabel(): string
    {
        return 'แคตตาล็อกแล็บ: ' . $this->name;
    }
}
