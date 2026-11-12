<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * ผลทดสอบพิสูจน์เชื้อหนองใน ตามแบบฟอร์ม FM-STI-WI08-02 REV1 (ห้อง Lab ขอ 2569-10-02)
 * เป็นใบงานของห้อง Lab — ไม่ใช่ผลทางการ ผลที่พยาบาล/หมอเห็นยังเป็น lab_subs.result
 */
class GcIdentification extends Model
{
    protected $table = 'gc_identifications';

    protected $fillable = [
        'lab_sub_id', 'lab_no', 'culture', 'typical', 'gndc', 'oxidase', 'superoxol',
        'glucose', 'maltose', 'sucrose', 'lactose', 'final_id', 'remark',
        'updated_by', 'reported_by', 'reported_at', 'approved_by', 'approved_at',
    ];

    protected $casts = ['reported_at' => 'datetime', 'approved_at' => 'datetime'];

    /** ช่องทดสอบ => ค่าที่เลือกได้ (ตามคำอธิบายท้ายแบบฟอร์ม) — ค่าอื่นถูกทิ้งตอนบันทึก */
    public const FIELDS = [
        'culture'   => ['G', 'NG', 'SO'],
        'typical'   => ['Y', 'N'],
        'gndc'      => ['P', 'N'],
        'oxidase'   => ['P', 'N'],
        'superoxol' => ['Negative', '1+', '2+', '3+', '4+'],
        'glucose'   => ['P', 'N'],
        'maltose'   => ['P', 'N'],
        'sucrose'   => ['P', 'N'],
        'lactose'   => ['P', 'N'],
        'final_id'  => ['NG', 'Non-NG'],
    ];

    /** ช่องที่ถ้าแก้แล้ว ต้อง Report/Approve ใหม่ */
    public const DATA_FIELDS = ['lab_no', 'culture', 'typical', 'gndc', 'oxidase', 'superoxol',
        'glucose', 'maltose', 'sucrose', 'lactose', 'final_id', 'remark'];

    /** กำหนดเป็น true ได้ในสคริปต์ทดสอบ (ตารางชั่วคราว) — ปกติเช็คจากฐานจริง */
    public static ?bool $tableReady = null;

    /**
     * ยังไม่ได้ migrate = หน้าจอเปิดได้แบบอ่านอย่างเดียว ไม่ให้ทั้งหน้าพัง 500
     * (ระบบใช้งานจริง migration รันตอนผู้ดูแลสะดวก ไม่ใช่ตอน deploy โค้ด)
     */
    public static function tableReady(): bool
    {
        if (self::$tableReady === null) {
            self::$tableReady = Schema::hasTable('gc_identifications');
        }
        return self::$tableReady;
    }

    /** มีค่าทดสอบอย่างน้อยหนึ่งช่องไหม — ใช้ตัดแถวว่างออกจากการ Report/Approve */
    public function hasData(): bool
    {
        foreach (self::DATA_FIELDS as $f) {
            if (trim((string) $this->{$f}) !== '') { return true; }
        }
        return false;
    }
}
