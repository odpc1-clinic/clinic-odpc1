<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * ติ๊กมือในตารางติดตามการรักษา (บัตร TB 01 แท็บ "ติดตาม")
 * ใช้กับรายการที่ระบบไม่มีที่เก็บผล (consent, EKG/QTcF) หรือไปตรวจที่อื่นแล้วไม่ได้ลงระบบ
 * ตารางยังไม่ขึ้น (รออนุมัติ migration) = ปุ่มติ๊กขึ้นข้อความ "รอเปิดใช้"
 */
class TbMonitorCheck extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    public static function ready(): bool
    {
        static $ready = null;
        return $ready ??= Schema::hasTable('tb_monitor_checks');
    }

    public function auditLabel(): string
    {
        return 'ตารางติดตาม TB: ' . $this->check_key . ' @ ' . $this->point_key;
    }
}
