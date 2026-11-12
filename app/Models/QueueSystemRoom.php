<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QueueSystemRoom extends Model
{
    protected $connection = 'queue_system';
    protected $table = 'rooms';
    protected $primaryKey = 'room_number';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['room_number', 'room_name'];
}
