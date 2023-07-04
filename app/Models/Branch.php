<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $fillable = ['company_id', 'store', 'address', 'name', 'type'];

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function providers()
    {
        return $this->hasMany(Provider::class);
    }

    public function carriers()
    {
        return $this->hasMany(Carrier::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function shops()
    {
        return $this->hasMany(Shop::class);
    }

    public function referralguides()
    {
        return $this->hasMany(ReferralGuide::class);
    }

    public function unities()
    {
        return $this->hasMany(Unity::class);
    }

    public function chartaccounts()
    {
        return $this->hasMany(ChartAccount::class);
    }
}
