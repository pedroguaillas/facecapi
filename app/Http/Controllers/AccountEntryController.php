<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\AccountEntry;
use App\Models\ChartAccount;
use App\Models\Company;
use App\Models\ShopRetentionItem;

class AccountEntryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($request)
    {
        // Diary Book
        // Is very good, including belong to branch, only require optimized
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);
        $branch = $company->branches->first();

        $accounts = ChartAccount::join('account_entry_items', 'account_entry_items.chart_account_id', 'chart_accounts.id')
            ->join('account_entries', 'account_entry_items.account_entry_id', 'account_entries.id')
            ->select(
                'account_entries.id',
                'account_entries.date',
                'account_entries.description',
                'chart_accounts.account',
                'chart_accounts.name',
                'account_entry_items.debit',
                'account_entry_items.have',
            )
            ->when($branch->id, function ($query, $id) {
                return $query->where('account_entries.branch_id', $id);
            })
            ->orderByRaw('account_entries.id')
            ->get();

        if (count($accounts)) {
            $newAccounts = array();
            $i = 0;
            $accountbefore = $accounts[$i]->id;

            while ($i < count($accounts)) {
                if ($i === 0 || $accountbefore !== $accounts[$i]->id) {
                    $account = [
                        'id' => $accounts[$i]->id,
                        'date' => $accounts[$i]->date,
                        'description' => $accounts[$i]->description,
                        'accountentryitems' => [
                            [
                                'account' => $accounts[$i]->account,
                                'name' => $accounts[$i]->name,
                                'debit' => $accounts[$i]->debit,
                                'have' => $accounts[$i]->have
                            ]
                        ]
                    ];
                    array_push($newAccounts, $account);
                } else {
                    array_push(
                        $newAccounts[count($newAccounts) - 1]['accountentryitems'],
                        [
                            'account' => $accounts[$i]->account,
                            'name' => $accounts[$i]->name,
                            'debit' => $accounts[$i]->debit,
                            'have' => $accounts[$i]->have
                        ]
                    );
                }

                $accountbefore = $accounts[$i]->id;
                $i++;
            }
            return response()->json([
                'accountentries' => $newAccounts,
                'company' => $company
            ]);
        } else {
            return response()->json([
                'accountentries' => [],
                'company' => $company
            ]);
        }

        // $accountentries = AccountEntry::where('branch_id', $branch->id)->get();

        // $result = array();
        // foreach ($accountentries as $accountentrie) {
        //     $accountentrie->accountentryitems = AccountEntryItem::join('chart_accounts', 'account_entry_items.chart_account_id', 'chart_accounts.id')
        //         ->select('account_entry_items.*', 'chart_accounts.account', 'chart_accounts.name')
        //         ->where('account_entry_items.account_entry_id', $accountentrie->id)
        //         ->get();
        //     array_push($result, $accountentrie);
        // }

        // Start Optimized ..............
        // $accountentries = AccountEntry::join('account_entry_items', 'account_entry_items.account_entry_id', 'account_entries.id')
        //     ->select('account_entries.*', '')
        //     ->where('branch_id', $branch->id)
        //     ->orderBy('account_entries.id')
        //     ->get();
        // End Optimized ..............
        // Require asigned to resource create or modify AccountEntryResources
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);

        // Validate-----Start
        $accountEntryItems = $request->get('accountingseats');

        $debit = 0;
        $have = 0;
        foreach ($accountEntryItems as $accountEntryItem) {
            $debit += $accountEntryItem['debit'] ?: 0;
            $have += $accountEntryItem['have'] ?: 0;
        }
        if (round($debit, 2) !== round($have, 2)) {
            return response()->json(['msg' => 'La suma del DEBE de ser igual al HABER'], 300);
        }
        // Validate-----End

        $accountEntry = new AccountEntry;
        $accountEntry->date = $request->get('date');
        $accountEntry->description = $request->get('description');
        $accountEntry->branch_id = $company->branches->first()->id;
        $accountEntry->save();

        $array = array();

        foreach ($accountEntryItems as $accountEntryItem) {
            $entry = [
                'chart_account_id' => $accountEntryItem['chart_account_id'],
                'debit' => $accountEntryItem['debit'] ?: 0,
                'have' => $accountEntryItem['have'] ?: 0
            ];
            array_push($array, $entry);
        }

        $accountEntry->accountentryitems()->createMany($array);

        // $accountEntry->accountentryitems = AccountEntryItem::join('chart_accounts', 'account_entry_items.chart_account_id', 'chart_accounts.id')
        //     ->select('account_entry_items.*', 'chart_accounts.account', 'chart_accounts.name')
        //     ->where('account_entry_items.account_entry_id', $accountEntry->id)
        //     ->get();

        // return response()->json(['accountEntry' => $accountEntry]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store_by_movement_id(int $id)
    {
        $movement = ShopRetentionItem::findOrFail($id);

        $auth = Auth::user();
        $level = $auth->companyusers->first();

        $company = Company::find($level->level_id);

        $accountEntry = new AccountEntry;
        $accountEntry->date = $movement->date;
        $accountEntry->description = $movement->description;
        $accountEntry->branch_id = $company->branches->first()->id;
        $accountEntry->save();

        $movement_items = ShopRetentionItem::join('products', 'products.id', 'movement_items.product_id')
            ->select(
                'active_account_id',
                'price',
                'quantity'
            )->where('movement_id', $id)->get();

        $array = array();
        $total = 0;
        foreach ($movement_items as $movement_item) {
            $sub_tot = $movement_item->price * $movement_item->quantity;
            array_push($array, [
                'chart_account_id' => $movement_item->active_account_id,
                'debit' => $sub_tot,
                'have' => 0
            ]);
            $total += $sub_tot;
        }

        $iva = round($total * .12, 2);
        // 1645 Iva Compra servicios
        array_push($array, [
            'chart_account_id' => 1645,
            'debit' => $iva,
            'have' => 0
        ]);

        $retentions = ShopRetentionItem::where('vaucher_id', $id)->get()->first();

        // Valor retenido en iva
        $valri = 0;
        // Valor retenido en renta
        $valrr = 0;
        // Valor retenido en renta
        $porrr = 0;

        foreach ($retentions->retentionitems as $retention_item) {
            if ((int)$retention_item->code === 2) {
                $valrr += $retention_item->value;
                $porrr = $retention_item->porcentage;
            } else {
                $valri += $retention_item->value;
            }
        }

        //Si tiene retencion en la renta
        if ($valrr > 0) {
            array_push($array, [
                //1823 Irfir 2% compras servicios
                'chart_account_id' => (int)$porrr === 1 ? 1822 : 1823,
                'debit' => 0,
                'have' => $valrr
            ]);
        }

        //Si tiene retencion en la IVA
        if ($valri > 0) {
            array_push($array, [
                //1826 Irfir 30% iva compras bienes
                'chart_account_id' => 1826,
                'debit' => 0,
                'have' => $valri
            ]);
        }

        array_push($array, [
            //1626 Pichincha (Cuenta corriente)
            'chart_account_id' => 1626,
            'debit' => 0,
            'have' => $total + $iva - $valri - $valrr
        ]);

        $accountEntry->accountentryitems()->createMany($array);
    }
}
