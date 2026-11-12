<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * คัดกรองซึมเศร้า 2Q / 9Q / 8Q (กรมสุขภาพจิต) ระหว่างรักษาวัณโรค
 * คำถาม/เกณฑ์อยู่ที่ App\Services\TbMonitor\DepressionScale — ที่นี่เก็บคำตอบ + คะแนนรวม
 */
class TbDepressionScreen extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected $casts = [
        'q2' => 'array', 'q9' => 'array', 'q8' => 'array',
        'q2_positive' => 'boolean', 'referred' => 'boolean',
    ];

    public static function ready(): bool
    {
        static $ready = null;
        return $ready ??= Schema::hasTable('tb_depression_screens');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function auditLabel(): string
    {
        return 'คัดกรองซึมเศร้า ' . $this->screen_date;
    }
}
