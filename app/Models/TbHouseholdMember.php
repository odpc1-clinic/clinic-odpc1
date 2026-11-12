<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * สมาชิกที่อยู่บ้านเดียวกับผู้ป่วยวัณโรค (Form 2 ข้อ 5)
 * แยกตารางเพราะหนึ่งใบคัดกรองมีได้หลายคน และแต่ละคนต้องตามผลคัดกรองรายคน
 */
class TbHouseholdMember extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    public function screening()
    {
        return $this->belongsTo(TbScreening::class, 'tb_screening_id');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function auditLabel(): string
    {
        return 'ผู้สัมผัสร่วมบ้าน ' . $this->name;
    }
}
