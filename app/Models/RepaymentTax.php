<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RepaymentTax extends Model
{
    protected $fillable = [
        'repayment_id',
        'iva_tax_code',
        'percentage',
        'base',
        'iva',
    ];

}
