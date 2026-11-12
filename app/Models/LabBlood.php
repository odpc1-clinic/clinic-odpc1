<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabBlood extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'lab_bloods';

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
    protected $fillable = ['patient_id', 'visit_id', 'report_by', 'approve_by', 'report_date', 'hiv', 'syphilis', 'rpr', 'pcr_specimen', 'pcr_result'];

    public static $option=[0=>'',1=>'Negative', 2=>'Positive', 3=>'Inconslusive'];

    public function visit(){
        return Visit::find($this->visit_id);
    }

    public function report_by(){
        if($this->report_by)
        return User::find($this->report_by)->name;
    }

    public function approve_by(){
        if($this->approve_by)
        return User::find($this->approve_by)->name;
    }
    
    public function hiv(){
        if($this->hiv)
        return self::$option[$this->hiv];
    }
    public function syphilis(){
        if($this->syphilis)
        return self::$option[$this->syphilis];
    }
}
