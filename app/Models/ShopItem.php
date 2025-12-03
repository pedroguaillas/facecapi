<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopItem extends Model
{
    protected $fillable = [
        'shop_id', 'product_id', 'iva',
        'quantity', 'price', 'discount'
    ];

    protected $casts = [
        'iva' => 'float',
        'price' => 'float',
        'discount' => 'float',
        'quantity' => 'float',
    ];

    /**
     * Get the post that owns the comment.
     */
    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Get the post that owns the comment.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
