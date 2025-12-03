<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SriCategory extends Model
{
    protected $fillable = [
        'code', 'type', 'description'
    ];
}
