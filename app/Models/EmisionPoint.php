<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmisionPoint extends Model
{
    protected $casts = [
        'lote' => 'integer'
    ];

    protected $fillable = [
        'branch_id', 'point', 'enabled',
        'invoice', 'creditnote', 'retention',
        'referralguide', 'settlementonpurchase',
        'lot', 'recognition'
    ];
}
