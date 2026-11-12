<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'addresses';

    /**
    * The database primary key value.
    *
    * @var string
    */
    protected $primaryKey = 'id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = ['patient_id','house_no', 'address', 'sub_district', 'district', 'province', 'zipcode'];


    public function sub_district(){
        $sub_district = \Baraear\ThaiAddress\Models\SubDistrict::find($this->sub_district);
        if($sub_district)
            return $sub_district->name;
    }
    
    public function district(){
        $sub_district = \Baraear\ThaiAddress\Models\District::find($this->district);
        if($sub_district)
            return $sub_district->name;
    }

    public function province(){
        $sub_district = \Baraear\ThaiAddress\Models\Province::find($this->province);
        if($sub_district)
            return $sub_district->name;
    }

    public function address(){
        return $this->house_no . ' '. $this->address . ' '.$this->sub_district() . ' '.$this->district() . ' '.$this->province() . ' '.$this->zipcode ;

    }
    public function fullAddress()
{
    // แยกเลขหมู่ (ตัวเลขตัวแรกของ address) แล้วเติมคำว่า "หมู่"
    if (preg_match('/^(\d+)\s+(.*)/u', $this->address, $matches)) {
        $moo = 'ที่อยู่ปัจจุบัน ' . $matches[1];
        $addr = $matches[2];
    } else {
        // ถ้าไม่มีเลขนำหน้า address เลย ก็ถือว่าไม่มีหมู่ (ไม่เติม)
        $moo = 'ที่อยู่ปัจจุบัน';
        $addr = $this->address;
    }

    return trim("{$this->house_no} {$moo} {$addr} {$this->sub_district()} {$this->district()} {$this->province()} {$this->zipcode}");
}



}
