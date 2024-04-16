<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $casts = [
        'price1' => 'float',
    ];

    protected $fillable = [
        'branch_id', 'category_id', 'code', 'type_product', 'name',
        'unity_id', 'price1', 'price2', 'price3', 'iva', 'ice', 'irbpnr',
        'entry_account_id', 'active_account_id', 'inventory_account_id',
        'stock'
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
}
