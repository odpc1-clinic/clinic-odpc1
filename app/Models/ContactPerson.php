<?php

namespace App\Models;

use App\Utillity;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;

class ContactPerson extends Model
{
    use Auditable;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'contact_people';

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
    protected $fillable = [
        'visit_id', 'date', 'name_surname', 'cpsex', 'type',
        // เพิ่ม oral_insertive_mt และ oral_receptive_mt
        'vagina_mt', 'mouth_mt', 'oral_insertive_mt', 'oral_receptive_mt', 
        'penis_mt', 'anus_mt', 'use_cd', 'unuse_cd', 'brea_slip_cd', 'unbrea_slip_cd', 'sex_condom', 'trush_tracks'
    ];

    // ... code อื่นๆ ...

    
    public static $cpsexOption=[0=>'',1=>'ชาย', 2=>'หญิง'];
    public static $typeOption=[0=>'',1=>'ผู้ให้บริการ', 2=>'สามี/ภรรยา',3=>'คู่นอนประจำ', 4=>'คู่นอนชั่วคราว' , 5=>'ลูกค้า'];

     public static $touchTracingOption = [0 => '', 1 => 'ฝากยา', 2 => 'มาตรวจพร้อมกันครั้งนี้', 3 => 'ออกใบติดตาม', 4 => 'ผู้ป่วยจะแจ้งเอง', 5 => 'จนท.ตามให้', 6 => 'ไม่สามารถติดตามได้', 7 => 'รักษาแล้ว'];


    public function sex_condom()
    {
        if ($this->sex_condom) {
            $result = json_decode($this->sex_condom, 1);
            return $result;
        }
        return [];
    }



    public function cpsex(){
       return self::$cpsexOption[$this->cpsex] ;
    }
    public function clearCheckBox(){
        // ... code เดิม ...
        $this->vagina_mt = null;
        $this->mouth_mt = null;
        
        // ✅ เพิ่ม 2 บรรทัดนี้เพื่อเคลียร์ค่าเมื่อ update
        $this->oral_insertive_mt = null;
        $this->oral_receptive_mt = null;

        $this->penis_mt = null;
        $this->anus_mt = null;
        $this->use_cd = null;
        $this->unuse_cd = null;
        $this->brea_slip_cd = null;
        $this->unbrea_slip_cd = null;
    }

    
    public static function format2DB($requestData){
        // $requestData['date'] = Utillity::th2dbDate($requestData['date']) ;
        return $requestData;
    }

    public  function formatDBBack(){
        // $this->date = Utillity::db2thDate($this->date) ;
    }
    public  function type(){
        return self::$typeOption[$this->type] ;
    }
}
