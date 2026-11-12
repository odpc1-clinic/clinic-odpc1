<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $table = 'db_country';
    protected $primaryKey = 'id';
    public $timestamps = false;
    
    protected $fillable = [
        'country_id', 'nation_name_en', 'nation_name_th' // เพิ่ม nation_name_th ตรงนี้
    ];
}
