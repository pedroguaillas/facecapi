<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lot extends Model
{
    protected $fillable = [
        'emision_point_id', 'serie',
        'authorization', 'authorized_at',
        'state'
    ];
}
