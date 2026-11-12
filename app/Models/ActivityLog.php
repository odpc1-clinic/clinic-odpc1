<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';

    protected $fillable = [
        'user_id', 'user_name', 'action', 'subject_type', 'subject_id',
        'patient_id', 'visit_id', 'subject_label', 'changes', 'ip',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // ป้ายชื่อชนิดข้อมูลแบบอ่านง่าย (ภาษาไทย) จาก subject_type
    public static $typeLabels = [
        'App\\Models\\Patient'        => 'ข้อมูลผู้ป่วย',
        'App\\Models\\Lab'            => 'หัวรายงาน Lab',
        'App\\Models\\LabSub'         => 'ผลตรวจ Lab',
        'App\\Models\\Diagnosi'       => 'การวินิจฉัย',
        'App\\Models\\VisitMedicine'  => 'การสั่งยา',
        'App\\Models\\ContactPerson'  => 'ผู้สัมผัส',
        'App\\Models\\VisitFollowup'  => 'การติดตาม',
        'App\\Models\\Billing'        => 'บิล/การเงิน',
        'App\\Models\\Medicine'       => 'ข้อมูลยา (Master)',
        'App\\Models\\MedicineLot'    => 'คลังยา (Lot)',
        'App\\Models\\ServicePrice'   => 'ราคาค่าบริการ',
        'App\\Models\\LabPrice'       => 'ราคากลาง Lab',
        'App\\Models\\TbRegistration' => 'บัตร TB 01',
    ];

    public function typeLabel(): string
    {
        return self::$typeLabels[$this->subject_type] ?? class_basename($this->subject_type);
    }

    public static $actionLabels = [
        'created' => ['สร้างใหม่', 'success'],
        'updated' => ['แก้ไข', 'warning'],
        'deleted' => ['ลบ', 'danger'],
        // เปิดดู/พิมพ์ผล HIV ที่ปิดไว้ (HivRevealController) — ไม่มีค่าผลใน changes
        'viewed'  => ['เปิดดู', 'info'],
    ];

    public function actionLabel(): string
    {
        return self::$actionLabels[$this->action][0] ?? $this->action;
    }

    public function actionColor(): string
    {
        return self::$actionLabels[$this->action][1] ?? 'secondary';
    }
}
