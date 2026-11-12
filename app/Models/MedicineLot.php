<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;

class MedicineLot extends Model
{
    use Auditable;

    // quantity_remaining ลดทุกครั้งที่จ่ายยา (มี log ละเอียดใน medicine_lot_movements แล้ว) จึงไม่ audit ซ้ำ
    protected static $auditExclude = ['quantity_remaining'];

    protected $fillable = [
        'medicine_id', 'lot_number', 'received_date', 'cost_price', 'sale_price',
        'quantity_received', 'quantity_remaining', 'expiry_date',
    ];

    protected $casts = [
        'received_date' => 'date',
        'expiry_date' => 'date',
    ];

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }

    // ใกล้หมดอายุภายใน N วันไหม (ไว้เตือน)
    public function isExpiringSoon(int $days = 90): bool
    {
        return $this->expiry_date && $this->expiry_date->isBetween(now(), now()->addDays($days));
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    // ===== วันที่: เก็บเป็น ค.ศ. แต่รับ/แสดงเป็น พ.ศ. =====
    // ห้ามเก็บ พ.ศ. ลง DB เด็ดขาด เพราะ isExpired()/isExpiringSoon() เทียบกับ now() ซึ่งเป็น ค.ศ.
    // ถ้าเก็บ 2570 ระบบจะคิดว่าอีก 500 กว่าปีถึงหมดอายุ ยาหมดอายุจะไม่ถูกเตือนเลย

    /** d/m/Y (พ.ศ.) หรือ Y-m-d (ค.ศ.) -> Y-m-d ค.ศ. สำหรับเก็บลง DB */
    public static function toDbDate($value): ?string
    {
        $v = trim((string) $value);
        if ($v === '' || $v === '0000-00-00') { return null; }

        // d/m/Y จากปฏิทินไทย -> ปีเป็น พ.ศ. ต้องลบ 543
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $v, $m)) {
            $year = (int) $m[3];
            if ($year > 2400) { $year -= 543; }
            return sprintf('%04d-%02d-%02d', $year, $m[2], $m[1]);
        }

        if (preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', $v, $m)) {
            $year = (int) $m[1];
            if ($year > 2400) { $year -= 543; }   // เผื่อมีคนส่ง พ.ศ. มาในรูปแบบ Y-m-d
            return sprintf('%04d-%s-%s', $year, $m[2], $m[3]);
        }

        return null;
    }

    /** ค.ศ. -> d/m/Y พ.ศ. สำหรับแสดงบนหน้าจอ */
    public static function toThDate($date): ?string
    {
        if (!$date) { return null; }
        $c = $date instanceof \Carbon\Carbon ? $date : \Carbon\Carbon::parse($date);
        return $c->format('d/m/') . ($c->year + 543);
    }

    public function getReceivedDateThAttribute(): ?string
    {
        return self::toThDate($this->received_date);
    }

    public function getExpiryDateThAttribute(): ?string
    {
        return self::toThDate($this->expiry_date);
    }
}
