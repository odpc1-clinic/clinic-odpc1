<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/** ผลเอกซเรย์ปอดของคอร์สการรักษา — บัตร TB 01 มีช่องไว้ 3 ครั้ง แต่ของจริงถ่ายมากกว่านั้น */
class TbXray extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected $casts = ['views' => 'array'];

    /** มีช่องหมายเหตุหรือยัง (migration 2026_10_05_300000) — ก่อนรัน หน้าบัตรไม่โชว์ช่องนี้ */
    public static function hasNote(): bool
    {
        static $has = null;
        return $has ??= \Illuminate\Support\Facades\Schema::hasColumn('tb_xrays', 'note');
    }

    /** ท่าถ่ายตามใบส่งตรวจเอกซเรย์ของศูนย์ */
    public static $viewOption = [
        'pa_upright'   => 'Chest PA Upright',
        'ap_supine'    => 'Chest AP Supine',
        'ap_upright'   => 'Chest AP Upright',
        'lt_lateral'   => 'Chest Lt. Lateral',
        'rt_lateral'   => 'Chest Rt. Lateral',
        'lt_decubitus' => 'Chest Lt. Lateral decubitus',
        'rt_decubitus' => 'Chest Rt. Lateral decubitus',
        'lordotic'     => 'Chest Lordotic view',
    ];

    public function registration()
    {
        return $this->belongsTo(TbRegistration::class, 'tb_registration_id');
    }

    public function auditLabel(): string
    {
        return 'ผล X-ray ' . ($this->xray_date ?: '');
    }
}
