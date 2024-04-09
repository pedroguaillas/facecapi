<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'quantity' => 'float',
        'price' => 'float',
        'discount' => 'float',
        'ice' => 'float',
    ];

    protected $fillable = [
        'order_id', 'product_id',
        'quantity', 'price',
        'discount', 'iva', 'ice'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
