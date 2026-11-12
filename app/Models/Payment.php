<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'billing_id', 'amount', 'payment_date', 'payment_method', 'received_by', 'remark'
    ];

    // เชื่อมกลับไปหาบิลหลัก
    public function billing()
    {
        return $this->belongsTo(Billing::class);
    }
}