<?php

namespace App\Models\Concerns;

use App\Models\Icd10Code;
use Illuminate\Support\Facades\DB;

/**
 * เอกสารที่ผูกรหัส ICD-10 ได้ (ใบวินิจฉัย · ใบคัดกรองวัณโรค · บัตร TB 01)
 *
 * โมเดลที่ใช้ต้องประกาศชื่อเจ้าของให้ตรงกับที่ controller ใช้บันทึก:
 *   protected static $icd10Owner = 'diagnosis';
 *
 * ทำไมต้องมี: รหัสเก็บคนละตาราง (icd10_entries) ผูกแบบ polymorphic จึงไม่มี
 * foreign key ให้ฐานข้อมูลลบตามให้เอง ถ้าลบเอกสารแล้วรหัสค้างอยู่
 * รายงานที่นับ "กี่รายเป็น A15.0" จะนับรวมเคสที่ถูกลบไปแล้วด้วยโดยไม่มีใครรู้
 */
trait HasIcd10Codes
{
    protected static function bootHasIcd10Codes(): void
    {
        static::deleted(function ($model) {
            DB::table('icd10_entries')
                ->where('owner_type', static::icd10Owner())
                ->where('owner_id', $model->getKey())
                ->delete();
        });
    }

    public static function icd10Owner(): string
    {
        return static::$icd10Owner;
    }

    /** รหัสที่เอกสารนี้เลือกไว้ รหัสหลักขึ้นก่อน */
    public function icd10Codes(): array
    {
        return Icd10Code::codesFor(static::icd10Owner(), $this->getKey());
    }

    /** เช่น "A15.0, U84.7" — ใช้โชว์ในตาราง/ใบสรุป */
    public function icd10Summary(bool $withText = false): string
    {
        return Icd10Code::summaryFor(static::icd10Owner(), $this->getKey(), $withText);
    }
}
