<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** สูตรยาวัณโรคมาตรฐาน — ใช้เป็นตัวเลือกในช่อง "สูตรยา" ของบัตร TB 01 */
class TbRegimen extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['active' => 'boolean', 'drugs' => 'array'];

    /**
     * กลุ่มสูตรที่เหมาะกับทะเบียนแต่ละประเภท
     * TB ทั่วไป -> DS-TB · MDR -> สูตรดื้อยาทั้งหมด · LTBI -> TPT
     */
    public static function forRegistryType(int $type)
    {
        $cats = [
            TbRegistration::TYPE_TB   => ['DS-TB'],
            TbRegistration::TYPE_MDR  => ['MDR-Longer regimen', 'MDR-Shorter Regimem', 'Bpal', 'BPalM'],
            TbRegistration::TYPE_LTBI => ['TPT'],
        ][$type] ?? [];

        return self::where('active', true)->whereIn('category', $cats)->orderBy('sort')->get();
    }

    /** กลุ่มยาที่คู่กับสูตร ไว้ดึงรายการยามาแสดง */
    public static function drugGroupFor(int $type): string
    {
        return [
            TbRegistration::TYPE_TB   => 'DS',
            TbRegistration::TYPE_MDR  => 'Resist',
            TbRegistration::TYPE_LTBI => 'TPT',
        ][$type] ?? 'DS';
    }
}
