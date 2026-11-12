<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QueueSystemQueue extends Model
{
    protected $connection = 'queue_system';
    protected $table = 'queues';
    public $timestamps = false; // ตารางนี้มีแค่ created_at ไม่มี updated_at

    protected $fillable = ['queue_number', 'room_number', 'status', 'call_count', 'is_calling'];

    public function room()
    {
        return $this->belongsTo(QueueSystemRoom::class, 'room_number', 'room_number');
    }
}
