<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;

class LabPrice extends Model
{
    use Auditable;

    use HasFactory;

    protected $fillable = ['name', 'price'];
}
