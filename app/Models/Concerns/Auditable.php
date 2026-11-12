<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;

/**
 * ติด trait นี้กับโมเดลที่ต้องการบันทึกว่า "ใครสร้าง/แก้ไข/ลบ + เมื่อไหร่ + เปลี่ยนอะไร"
 * ทำงานอัตโนมัติผ่าน model events โดยไม่ต้องแก้ controller
 *
 * ปรับแต่งได้:
 *   protected static $auditExclude = ['stock']; // ฟิลด์ที่ไม่อยากให้ log (เช่นค่าที่ระบบอัปเดตบ่อยๆ)
 *   public function auditLabel(): string { ... }  // ป้ายอ่านง่าย เช่น HN + ชื่อ
 */
trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(function ($model) {
            $model->recordActivity('created');
        });

        static::updated(function ($model) {
            $model->recordActivity('updated');
        });

        static::deleted(function ($model) {
            $model->recordActivity('deleted');
        });
    }

    public function recordActivity(string $action): void
    {
        try {
            $ignore = array_merge(
                ['id', 'created_at', 'updated_at', 'password', 'remember_token', 'photo'],
                property_exists(static::class, 'auditExclude') ? static::$auditExclude : []
            );

            $changes = [];

            if ($action === 'updated') {
                foreach ($this->getChanges() as $key => $new) {
                    if (in_array($key, $ignore, true)) {
                        continue;
                    }
                    $changes[$key] = ['old' => $this->getOriginal($key), 'new' => $new];
                }
                // ถ้าไม่มีฟิลด์สำคัญเปลี่ยน (เช่นแก้แค่ timestamp) ไม่ต้องบันทึก
                if (empty($changes)) {
                    return;
                }
            } elseif ($action === 'created') {
                foreach ($this->getAttributes() as $key => $value) {
                    if (in_array($key, $ignore, true)) {
                        continue;
                    }
                    if ($value === null || $value === '') {
                        continue;
                    }
                    $changes[$key] = ['old' => null, 'new' => $value];
                }
            } elseif ($action === 'deleted') {
                // เก็บค่าทั้งแถวไว้ก่อนมันหายไป — ไม่งั้น log บอกได้แค่ว่า "ลบ id 16102"
                // แต่ไม่มีใครรู้ว่าในแถวนั้นมีอะไร กดพลาดทีเดียวคือหายถาวร
                //
                // เจอจริง 2569-09-29: เจ้าหน้าที่ลบใบวินิจฉัยของ visit 12563 โดยไม่ตั้งใจ
                // log มีแค่เลข id ต้องไปไล่ binlog ของ MySQL ถึงได้ข้อมูลกลับมา
                // ซึ่งรอดเพราะบังเอิญยังไม่ถูกหมุนทิ้ง ไม่ใช่เพราะระบบออกแบบมาให้กู้ได้
                //
                // ใส่ค่าเดิมไว้ฝั่ง old ให้ตรงกับความหมาย "เคยมีค่านี้ ตอนนี้ไม่มีแล้ว"
                // หน้าประวัติการแก้ไขจะแสดงเป็นข้อความขีดฆ่าสีแดงเองโดยไม่ต้องแก้หน้าจอ
                foreach ($this->getAttributes() as $key => $value) {
                    if (in_array($key, $ignore, true)) {
                        continue;
                    }
                    if ($value === null || $value === '') {
                        continue;
                    }
                    $changes[$key] = ['old' => $value, 'new' => null];
                }
            }

            // ผูก log เข้ากับ "เคส" (คนไข้/visit) เพื่อให้ดูประวัติเฉพาะเคสได้
            $patientId = null;
            $visitId   = null;
            if (method_exists($this, 'auditContext')) {
                $ctx = $this->auditContext();
                $patientId = $ctx['patient_id'] ?? null;
                $visitId   = $ctx['visit_id'] ?? null;
            } else {
                $patientId = $this->getAttribute('patient_id');
                $visitId   = $this->getAttribute('visit_id');
            }
            // มี visit แต่ไม่รู้ patient -> ดึงจาก visit ให้
            if (!$patientId && $visitId) {
                $patientId = optional(\App\Models\Visit::find($visitId))->patient_id;
            }

            ActivityLog::create([
                'user_id'       => auth()->id(),
                'user_name'     => optional(auth()->user())->name,
                'action'        => $action,
                'subject_type'  => get_class($this),
                'subject_id'    => $this->getKey(),
                'patient_id'    => $patientId,
                'visit_id'      => $visitId,
                'subject_label' => method_exists($this, 'auditLabel') ? $this->auditLabel() : null,
                'changes'       => $changes ?: null,
                'ip'            => optional(request())->ip(),
            ]);
        } catch (\Throwable $e) {
            // ห้ามให้การบันทึก log ทำให้งานหลัก (บันทึกผล/บิล/คนไข้) ล้มเหลว
        }
    }
}
