<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'branch_id', 'state', 'type_identification', 'identication',
        'name', 'address', 'phone', 'email', 'accounting', 'discount',
        'rent_retention', 'iva_retention'
    ];
}
