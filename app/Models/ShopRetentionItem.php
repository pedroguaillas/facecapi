<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopRetentionItem extends Model
{
    protected $fillable = [
        'code', 'tax_code', 'base',
        'porcentage', 'value', 'shop_id'
    ];

    protected $casts = [
        'base' => 'float',
        'porcentage' => 'float',
        'value' => 'float',
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
