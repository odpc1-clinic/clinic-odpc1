<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicineLotMovement extends Model
{
    protected $fillable = [
        'medicine_lot_id', 'medicine_id', 'visit_id', 'type', 'qty', 'user_id', 'note',
    ];

    public function lot()
    {
        return $this->belongsTo(MedicineLot::class, 'medicine_lot_id');
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }
}
