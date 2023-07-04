<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $fillable = [
        'product_id', 'model_id', 'type', 'quantity',
        'price', 'date', 'code_provider'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
