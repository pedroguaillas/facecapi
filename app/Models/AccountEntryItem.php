<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountEntryItem extends Model
{
    protected $fillable = ['account_entry_id', 'chart_account_id', 'debit', 'have'];

    public function accountentry()
    {
        return $this->belongsTo(AccountEntry::class);
    }
}
