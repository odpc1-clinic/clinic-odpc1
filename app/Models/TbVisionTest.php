<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * ผลตรวจสายตา (VA + ตาบอดสี) ของคอร์สการรักษาวัณโรค
 * Ethambutol / Linezolid มีพิษต่อเส้นประสาทตา — คลินิกตรวจทุกรายก่อนเริ่มยา และทุกเดือนระหว่างได้ยาสองตัวนี้
 */
class TbVisionTest extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    public static $colorOption = ['normal' => 'ปกติ', 'abnormal' => 'ผิดปกติ'];

    public static function ready(): bool
    {
        static $ready = null;
        return $ready ??= Schema::hasTable('tb_vision_tests');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function colorText(): string
    {
        $t = self::$colorOption[$this->color_result] ?? '';
        if ($this->color_plates_total) {
            $t .= ($t ? ' ' : '') . '(' . (int) $this->color_plates_correct . '/' . (int) $this->color_plates_total . ' แผ่น)';
        }
        return $t ?: '—';
    }

    public function auditLabel(): string
    {
        return 'ตรวจสายตา ' . $this->test_date;
    }
}
