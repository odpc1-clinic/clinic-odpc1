<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PatientNoteRead extends Model
{
    protected $table = 'patient_note_reads';

    public $timestamps = false;

    protected $fillable = ['patient_note_id', 'user_id', 'read_at'];

    protected $casts = [
        'read_at' => 'datetime',
    ];
}
