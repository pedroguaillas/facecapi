<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralGuideItem extends Model
{
    protected $fillable = [
        'referral_guide_id',
        'product_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'float',
    ];

    public function referralguide()
    {
        return $this->belongsTo(ReferralGuide::class);
    }
}
