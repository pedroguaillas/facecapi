<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'branch_id',
        'category_id',
        'code',
        'type_product',
        'name',
        'unity_id',
        'price1',
        'price2',
        'price3',
        'iva',
        'ice',
        'irbpnr',
        'entry_account_id',
        'active_account_id',
        'inventory_account_id',
        'stock',
        'tourism',
        // Obligatorio productos con el IVA 5%
        'aux_cod',
    ];

    protected $casts = [
        'price1' => 'float',
        'tourism' => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function unity()
    {
        return $this->belongsTo(Unity::class);
    }

    public function inventories()
    {
        return $this->hasMany(Inventory::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function shopItems()
    {
        return $this->hasMany(ShopItem::class);
    }

    public function referralGuideItems()
    {
        return $this->hasMany(ReferralGuideItem::class);
    }
}
