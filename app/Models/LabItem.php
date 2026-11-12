<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabItem extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'lab_items';

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
    protected $fillable = ['lab_id', 'specimen_from', 'other_specimen', 'gram_stain', 'gncd', 'pmn', 'wet_preparation', 'tv', 'clue_cell', 'koh', 'yeast_hyphae', 'bacterial_ulture', 'bc_result', 'other_test', 'other_result'];

    public static $specimenFromOption=[1=>'Urethra', 2=>'Vegina', 3=>'Cervix', 4=>'Anus', 5=>'Pharaynx', 6=>'Other'];

    public function clearCheckBox(){
        $this->gram_stain = null;
        $this->wet_preparation = null;
        $this->koh = null;
        $this->bacterial_ulture = null;
        $this->other_result = null;
    }

    public function visit(){
        $lab = Lab::find($this->lab_id);
        return Visit::find($lab->visit_id)->first();
    }
    
    public function specimenFrom(){
        $specimen_from = self::$specimenFromOption[$this->specimen_from];
        if($this->other_specimen){
            $specimen_from = $specimen_from.":".$this->other_specimen;
        }
        return $specimen_from;
    }

}
