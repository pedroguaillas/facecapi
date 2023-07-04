<?php

namespace App\Http\Controllers;

use App\Models\AccountEntryItem;
use Illuminate\Support\Facades\DB;

class AccountEntryItemController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $accountEntryItems = AccountEntryItem::join('chart_accounts', 'account_entry_items.chart_account_id', 'chart_accounts.id')
            ->select('chart_accounts.account', 'chart_accounts.name',  DB::raw('SUM(account_entry_items.debit) as debit'), DB::raw('SUM(account_entry_items.have) as have'))
            ->groupby('chart_accounts.account', 'chart_accounts.name')
            ->get();
        return response()->json(['accountEntryItems' => $accountEntryItems]);
    }
}
