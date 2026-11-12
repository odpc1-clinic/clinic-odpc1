<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vistit extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'vistits';

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
    protected $fillable = ['patient_id', 'know_from', 'send_from', 'other_from', 'reason_sti', 'reason_sti_other', 'reason_vct', '\reason_vct_other', 'sti_hostory', 'contraceptive_method', 'LMP', 'symptom', 'diagnosis', '\term_syphilis', 'disease_state', 'treatment', 'disease_state_other', 'consultation', 'hiv_sti_test', 'hiv_sti_test_date', 'hiv_sti_test_resule', '\touch_tracing', 'touch_tracing_fail', 'provide_condom_site', 'provide_condom_quantity', 'provide_lubricant_quantity', 'appointment', 'appointment_reason'];

    
}
