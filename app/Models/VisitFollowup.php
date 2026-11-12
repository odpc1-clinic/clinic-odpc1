<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;

class VisitFollowup extends Model
{
    use Auditable;

    use HasFactory;

    protected $fillable = [
        'visit_id',
        'method',
        'followup_count',
        'status',
        'remark'  // เพิ่มหมายเหตุใน fillable
    ];

    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }
}
