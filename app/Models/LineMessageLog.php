<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LineMessageLog extends Model
{
    protected $fillable = [
        'patient_id', 'line_user_id', 'type', 'purpose', 'message', 'success', 'error',
    ];

    protected $casts = [
        'success' => 'boolean',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}
