<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisitCertificate extends Model
{
    protected $fillable = ['visit_id', 'issued_by', 'data'];

    protected $casts = [
        'data' => 'array',
    ];

    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }

    public function issuedByUser()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
