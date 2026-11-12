<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PatientNote extends Model
{
    protected $table = 'patient_notes';

    protected $fillable = [
        'patient_id', 'user_id', 'body', 'is_resolved', 'resolved_by', 'resolved_at',
    ];

    protected $casts = [
        'is_resolved' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function resolvedByUser()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function reads()
    {
        return $this->hasMany(PatientNoteRead::class, 'patient_note_id');
    }
}
