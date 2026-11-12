<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * หลักฐานกินยาที่คนไข้ส่งเข้ามาทาง LINE (Video DOT)
 *
 * dot_date เก็บเป็น พ.ศ. เหมือนวันที่อื่นในระบบ ส่วน received_at เป็นเวลาจริงที่ส่งเข้ามา
 * (ใช้แยกกรณีคนไข้ส่งดึก ๆ แล้วเรามาเปิดดูวันถัดไป)
 */
class TbDotSubmission extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected $casts = [
        'ai_result'    => 'array',
        'received_at'  => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function registration()
    {
        return $this->belongsTo(TbRegistration::class, 'tb_registration_id');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function confirmer()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /** ที่อยู่ไฟล์ในดิสก์ local (เก็บเข้ารหัส เหมือนรูปผังครอบครัว) */
    public function storagePath(): ?string
    {
        return $this->file ? "private/tb_dot/{$this->file}" : null;
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }

    public function auditLabel(): string
    {
        return 'หลักฐาน DOT วันที่ ' . \App\Utillity::db2thDate($this->dot_date);
    }
}
