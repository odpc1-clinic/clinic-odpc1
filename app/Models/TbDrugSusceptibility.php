<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * ผลทดสอบความไวต่อยา (DST)
 * ผลรายยาเก็บเป็น JSON เพราะรายการยาต่างกันระหว่าง TB 01 กับ PMTB 01 และมียาใหม่เพิ่มเรื่อย ๆ
 */
class TbDrugSusceptibility extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected $casts = ['results' => 'array'];

    /**
     * วิธีทดสอบความไวต่อยา
     *
     * ท้ายบัตร TB 01 เขียนรวมว่า "Molecular" แต่ในทางปฏิบัติต้องรู้ว่าวิธีไหน
     * เพราะแปลผลต่างกัน จึงแยกเป็น Xpert / LPA / PCR (คลินิกขอ 2569-09-29)
     */
    public static $methodOption = [
        1 => 'Solid',
        2 => 'Liquid',
        4 => 'Xpert MTB/RIF',
        5 => 'LPA',
        6 => 'PCR',
    ];

    /** ค่าเดิมที่เคยบันทึกไว้ก่อนแยกวิธี — ยังแสดงได้ แต่ไม่ให้เลือกใหม่ */
    public static $methodLegacy = [3 => 'Molecular (ไม่ระบุวิธี)'];

    public static function methodName($value): string
    {
        return self::$methodOption[$value] ?? self::$methodLegacy[$value] ?? '-';
    }

    /** ยาที่บัตร TB 01 มีช่องไว้ให้ (ยาตัวอื่นเพิ่มในช่องหมายเหตุ) */
    public static $drugs = ['S', 'H', 'R', 'E', 'Z', 'Ofx', 'Km', 'Cs', 'Eto', 'PAS', 'Cm', 'Lfx'];

    /** ผลที่ใช้กันจริง */
    public static $resultOption = ['S' => 'ไว (S)', 'R' => 'ดื้อ (R)', 'I' => 'ก้ำกึ่ง (I)'];

    public function registration()
    {
        return $this->belongsTo(TbRegistration::class, 'tb_registration_id');
    }

    public function auditLabel(): string
    {
        return 'ผลความไวต่อยา LSN ' . ($this->cs_no ?: '-');
    }
}
