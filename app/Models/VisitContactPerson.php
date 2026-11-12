<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisitContactPerson extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'visit_contact_people';

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
    protected $fillable = ['visit_id', 'CContactPerson_id'];

    
}
