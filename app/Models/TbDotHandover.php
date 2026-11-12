<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * เรื่องส่งเวรของคนไข้ DOT (พยาบาลขอ 2569-09-30)
 *
 * ต่างจากหมายเหตุรายวัน (TbDotDay.note) ตรงที่ไม่ผูกกับวัน — ค้างโชว์ในหน้า DOT ทุกวัน
 * จนกว่าจะมีคนติ๊ก "ปัญหาสิ้นสุด" แล้วเก็บไว้เป็นประวัติ ไม่ลบ
 */
class TbDotHandover extends Model
{
    // แก้ข้อความส่งเวรได้แล้ว (พิมพ์ผิดตอนรีบกด 2569-10-07) — เก็บประวัติว่าใครแก้จากอะไรเป็นอะไร
    use Auditable;

    protected $guarded = ['id'];

    protected $casts = ['resolved_at' => 'datetime'];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * "ของวันที่ 29/10/2569" เมื่อพยาบาลส่งเวรจากหน้าวันอื่น (ไม่ใช่วันที่กด) · วันเดียวกัน/แถวเก่า = ''
     * dot_date เก็บ พ.ศ. (แบบ tb_dot_days) · created_at เป็น ค.ศ.
     */
    public function forDateText(): string
    {
        $d = (string) ($this->getRawOriginal('dot_date') ?? $this->dot_date ?? '');
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $d, $m)) { return ''; }
        $made = $this->created_at ? ($this->created_at->year + 543) . $this->created_at->format('-m-d') : '';
        return $made === "{$m[1]}-{$m[2]}-{$m[3]}" ? '' : "ของวันที่ {$m[3]}/{$m[2]}/{$m[1]}";
    }

    public function auditLabel(): string
    {
        return 'เรื่องส่งเวร DOT (บัตร TB ' . $this->tb_registration_id . ')';
    }
}
