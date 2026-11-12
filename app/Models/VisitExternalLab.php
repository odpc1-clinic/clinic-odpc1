<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/** รายการแล็บนอกที่สั่งจริงใน visit หนึ่ง */
class VisitExternalLab extends Model
{
    use Auditable;

    protected $table = 'visit_external_labs';

    protected $fillable = [
        'visit_id', 'external_lab_test_id', 'vendor',
        'code', 'name', 'specimen', 'ref_price', 'price',
        'result', 'remark', 'sent_date', 'result_date',
        'ordered_by', 'result_by',
    ];

    protected $casts = ['ref_price' => 'decimal:2', 'price' => 'decimal:2'];

    public function auditLabel(): string
    {
        return 'Lab นอก: ' . $this->name;
    }

    public function auditContext(): array
    {
        $visit = Visit::find($this->visit_id);
        return ['patient_id' => $visit->patient_id ?? null, 'visit_id' => $this->visit_id];
    }

    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }

    public function test()
    {
        return $this->belongsTo(ExternalLabTest::class, 'external_lab_test_id');
    }

    /** ผลออกแล้วหรือยัง (แล็บนอกไม่ต้องรอห้อง Lab อนุมัติ) */
    public function hasResult(): bool
    {
        return trim((string) $this->result) !== '';
    }

    /**
     * ข้อความผลที่ระบบใส่ให้เมื่อแนบไฟล์ผลกับรายการนี้ (คลินิกขอ 2569-10-30: อัปไฟล์แล้วไม่ต้องพิมพ์ผลซ้ำ)
     * ใส่เป็นข้อความจริงในช่อง result เพื่อให้ทุกที่ที่เช็ค hasResult() (ใบพิมพ์ ห้องการเงิน ห้องยา รายการรอผล) เห็นว่ามีผลแล้ว
     */
    public const FILE_RESULT = 'ผลตามไฟล์แนบ';

    public function files()
    {
        return $this->hasMany(TbLabFile::class, 'external_lab_id');
    }

    /** แนบไฟล์ผลแล้ว — ยังไม่มีผล = ใส่ "ผลตามไฟล์แนบ" ให้ · พิมพ์ผลไว้แล้วไม่ทับ */
    public function markResultByFile(?string $testDateBe): void
    {
        if ($this->hasResult()) { return; }
        $this->result      = self::FILE_RESULT;
        $this->result_date = $testDateBe ?: ((now()->year + 543) . now()->format('-m-d'));
        $this->result_by   = \Illuminate\Support\Facades\Auth::id();
        $this->save();
    }

    /** ลบไฟล์ผลตัวสุดท้ายของรายการนี้ — ผลที่ระบบใส่ให้ต้องกลับเป็นรอผล (ผลที่คนพิมพ์เองไม่แตะ) */
    public function releaseFileResult(): void
    {
        if ($this->result !== self::FILE_RESULT || $this->files()->exists()) { return; }
        $this->result = null;
        $this->result_date = null;
        $this->result_by = null;
        $this->save();
    }

    // ===== วันที่ =====
    // ในระบบนี้วันที่เก็บเป็น พ.ศ. รูปแบบ Y-m-d ส่วนหน้าจอกรอก/แสดงเป็น d/m/Y
    // ต้องกัน '0000-00-00' ด้วย เพราะเป็นค่าที่ MySQL ใส่ให้เองเมื่อได้รูปแบบที่อ่านไม่ออก

    /** d/m/Y (หรือ Y-m-d อยู่แล้ว) -> Y-m-d สำหรับเก็บลง DB */
    public static function toDbDate($value): ?string
    {
        $v = trim((string) $value);
        if ($v === '' || $v === '0000-00-00') { return null; }

        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $v, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        if (preg_match('#^\d{4}-\d{2}-\d{2}$#', $v)) { return $v; }

        return null;
    }

    /** Y-m-d -> d/m/Y สำหรับแสดงบนหน้าจอ/ใบพิมพ์ */
    public static function toThDate($value): ?string
    {
        $v = trim((string) $value);
        if ($v === '' || $v === '0000-00-00') { return null; }

        if (preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', $v, $m)) {
            return $m[3] . '/' . $m[2] . '/' . $m[1];
        }
        return $v;
    }

    public function getSentDateThAttribute(): ?string
    {
        return self::toThDate($this->sent_date);
    }

    public function getResultDateThAttribute(): ?string
    {
        return self::toThDate($this->result_date);
    }
}
