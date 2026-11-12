<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ปฏิทินกินยารายวัน (ระยะเข้มข้น/ระยะต่อเนื่อง)
 *
 * ไม่ใส่ Auditable เพราะแถวนี้เกิดวันละหลายแถวต่อคนไข้หนึ่งคน
 * ถ้าบันทึก activity ทุกครั้งที่ติ๊ก log จะบวมเร็วมากโดยไม่มีใครย้อนดู
 */
class TbDotDay extends Model
{
    protected $guarded = ['id'];

    /** 1 = ระยะเข้มข้น · 2 = ระยะต่อเนื่อง */
    public static $phaseOption = [
        1 => 'ระยะเข้มข้น (Intensive Phase)',
        2 => 'ระยะต่อเนื่อง (Continuation Phase)',
    ];

    /** สัญลักษณ์ตามที่ระบุท้ายบัตร TB 01 */
    public static $markOption = [
        'taken'     => '✓ ผู้ป่วยกินยา',
        'start'     => '✗ วันเริ่มกินยา',   // ตัดส่วน "วันที่คาดว่าจะกินเดือนถัดไป" ออกตามที่คลินิกขอ 2569-09-30
        'dispensed' => '– วันที่จ่ายยาให้',
        'missed'    => '0 ผู้ป่วยไม่กินยา',
        // เสาร์-อาทิตย์/วันหยุด: คนไข้กินยาเองโดยไม่มีคนกำกับ พยาบาลมายืนยันวันทำการ (คลินิกขอ 2569-10-01)
        // แยกจาก ✓ เพื่อให้รู้ว่าวันไหนพยาบาลเห็นเอง วันไหนคนไข้กินเองแล้วมายืนยันทีหลัง
        'self'      => '⌂ คนไข้กินยาเอง',
    ];

    /** ตัวอักษรที่แสดงในช่องปฏิทิน */
    public static $markChar = [
        'taken' => '✓', 'start' => '✗', 'dispensed' => '–', 'missed' => '0', 'self' => '⌂',
    ];

    /**
     * dot_date เก็บเป็น พ.ศ. เสมอ — ถ้ามีทางไหนส่ง ค.ศ. มา (เคยเจอแถวปี 2026) แปลงให้ก่อนเก็บ
     * ไม่งั้นแถวนั้นไม่ขึ้นในปฏิทิน/บอร์ด และรายงานรายเดือนนับตกหล่น (รีวิว 2569-09-30)
     */
    protected static function booted()
    {
        static::saving(function (TbDotDay $d) {
            $v = (string) $d->dot_date;
            if (preg_match('/^(\d{4})(-\d{2}-\d{2})/', $v, $m) && (int) $m[1] < 2500) {
                $d->dot_date = ((int) $m[1] + 543) . $m[2];
            }
        });
    }

    public function registration()
    {
        return $this->belongsTo(TbRegistration::class, 'tb_registration_id');
    }
}
