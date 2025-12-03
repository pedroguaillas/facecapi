<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Repayment extends Model
{
    protected $fillable = [
        'order_id',
        'type_id_prov',
        'identification',
        'cod_country',
        'type_prov',
        'type_document',
        'sequential',
        'date',
        'authorization',
    ];

    public function repaymenttaxes()
    {
        return $this->hasMany(RepaymentTax::class);
    }
}
