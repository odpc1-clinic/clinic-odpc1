<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** รายการ "สาเหตุการมาที่คลินิก" TMC (master list เพิ่มเองได้ ไม่ซ้ำ) */
class TmcReason extends Model
{
    protected $table = 'tmc_reasons';
    protected $fillable = ['name'];
}
