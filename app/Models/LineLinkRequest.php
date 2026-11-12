<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** รหัสผูก LINE ที่ส่งมาจากฟอร์มใน LINE — ดู line:pull-links */
class LineLinkRequest extends Model
{
    protected $fillable = [
        'relay_id', 'line_user_id', 'line_display_name', 'code',
        'status', 'patient_id', 'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}
