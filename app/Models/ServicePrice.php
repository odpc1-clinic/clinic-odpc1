<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;

class ServicePrice extends Model
{
    use Auditable;

    protected $fillable = ['name', 'price', 'is_active', 'is_package', 'sort_order', 'clinics'];

    /** คลินิกที่เลือกได้ในหน้าตั้งค่าค่าบริการ — ตรงกับ visits.clinic */
    public const CLINICS = [1 => 'STI', 2 => 'TB', 3 => 'TMC'];

    /**
     * ใช้กับคลินิกนี้ไหม — clinics ว่าง = ทุกคลินิก, "1,2" = STI + วัณโรค (TMC ขอซ่อนของ STI 2569-09-30)
     */
    public function availableFor(int $clinic): bool
    {
        $list = array_filter(array_map('trim', explode(',', (string) $this->clinics)));
        return ! $list || in_array((string) $clinic, $list, true);
    }

    protected $casts = [
        'is_active' => 'boolean',
        'is_package' => 'boolean',
        'price' => 'decimal:2',
    ];
}
