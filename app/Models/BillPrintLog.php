<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillPrintLog extends Model
{
    protected $fillable = ['billing_id', 'printed_by', 'reason'];

    public function billing()
    {
        return $this->belongsTo(Billing::class);
    }
}
