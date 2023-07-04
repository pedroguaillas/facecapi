<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\ChartAccount;
use App\Models\Company;
use Barryvdh\DomPDF\Facade as PDF;

class ChartAccountController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();

        $company = Company::find($level->level_id);
        $branch = $company->branches->first();

        //...............................................

        // $chartaccounts = ChartAccount::leftJoin('account_entry_items', 'account_entry_items.chart_account_id', 'chart_accounts.id')
        //     ->select(
        //         'chart_accounts.id',
        //         'chart_accounts.account',
        //         'chart_accounts.name',
        //         DB::raw('SUM(account_entry_items.debit) as debit'),
        //         DB::raw('SUM(account_entry_items.have) as have')
        //     )
        //     ->whereIn('account_entry_items.account_entry_id', $this->to_array_object_ids($account_entries))
        //     // ->whereIn('account_entry_items.account_entry_id', '(SELECT account_entries.id FROM account_entries WHERE branch_id = ' . $branch->id . ')')
        //     ->whereAnd('type', $company->type)
        //     // ->when($branch, function ($query, $branch) {
        //     //     return $query->whereNull('branch_id')
        //     //         ->orWhere('branch_id', $branch->id);
        //     // })
        //     ->groupBy('chart_accounts.id', 'chart_accounts.account', 'chart_accounts.name')
        //     ->orderByRaw('sort_account DESC')
        //     ->get();

        //...............................................
        // Nota: No es necesario retringir la fecha ya que el plan de cuentas refleja todo el monto de todos los movimientos

        $sql = "SELECT ca.id, ca.account, ca.name, ";
        $sql .= "SUM(CASE WHEN aei.account_entry_id IN (SELECT iae1.id FROM account_entries AS iae1 WHERE iae1.branch_id = $branch->id) THEN aei.debit END) AS debit, ";
        $sql .= "SUM(CASE WHEN aei.account_entry_id IN (SELECT iae2.id FROM account_entries AS iae2 WHERE iae2.branch_id = $branch->id) THEN aei.have END) AS have ";
        $sql .= "FROM chart_accounts AS ca LEFT JOIN account_entry_items AS aei ";
        $sql .= "ON ca.id = aei.chart_account_id ";
        $sql .= "WHERE economic_activity = '$company->economic_activity' AND (ca.branch_id IS NULL OR ca.branch_id = $branch->id) ";
        $sql .= "GROUP BY ca.id, ca.account, ca.name ";
        $sql .= "ORDER BY sort_account DESC";

        // Query to database
        $chartaccounts = DB::select($sql);

        return $this->account($chartaccounts);
    }

    private function to_array_object_ids($account_entries)
    {
        $result = [];
        foreach ($account_entries as $account) {
            array_push($result, $account->id);
        }

        return $result;
    }

    public function indexPdf()
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();

        $company = Company::find($level->level_id);

        $charts = $this->index();
        //------------Array Encode
        $charts = json_decode(json_encode($charts), true);

        $pdf = PDF::loadView('charts', compact('charts', 'company'));

        $dom_pdf = $pdf->getDomPDF();
        $canvas = $dom_pdf->get_canvas();

        //For Header
        $header = $canvas->open_object();
        $canvas->page_text(30, 20, $company['company'], null, 10, array(0, 0, 0));
        $canvas->page_text(30, 35, "Fecha de impresión: " . date('d/m/Y', strtotime('+5 hours')), null, 8, array(0, 0, 0));
        $canvas->line(30, 55, 560, 55, array(.3, .3, .3), 1);
        $canvas->close_object();
        $canvas->add_object($header, "all");

        //For Footer
        $footer = $canvas->open_object();
        $canvas->line(30, 805, 560, 805, array(.3, .3, .3), 1);
        $canvas->page_text(30, 810, "www.auditwhole.com | Contabilidad en línea.", null, 8, array(0, 0, 0));
        $canvas->page_text(520, 810, "Página {PAGE_NUM} de {PAGE_COUNT}", null, 8, array(0, 0, 0));
        $canvas->close_object();
        $canvas->add_object($footer, "all");

        return $pdf->stream();
    }

    /**
     * Store a resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);
        $branch = $company->branches->first();

        // $branch->chartaccounts()->create($request->all());
        $branch->chartaccounts()->create([
            'account' => $request->get('account'),
            'type' => $company->type,
            'name' => $request->get('name'),
            'sort_account' => $this->create_short_account($request->get('account'))
        ]);

        return $this->index();

        // return response()->json(['message' => 'Store ChartAccount']);
    }

    private function create_short_account($account)
    {
        $result = '';
        $array = explode('.', $account);

        foreach ($array as $element) {
            $result = $result . str_pad($element, 2, 0, STR_PAD_LEFT);
        }

        return $result;
    }

    public function balancepurchase()
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);
        $branch = $company->branches->first();

        $accounts = ChartAccount::join('account_entry_items', 'account_entry_items.chart_account_id', 'chart_accounts.id')
            ->join('account_entries', 'account_entry_items.account_entry_id', 'account_entries.id')
            ->select(
                'chart_accounts.account',
                'chart_accounts.name',
                DB::raw('SUM(account_entry_items.debit) as debit'),
                DB::raw('SUM(account_entry_items.have) as have')
            )
            ->when($branch->id, function ($query, $id) {
                return $query->where('account_entries.branch_id', $id);
            })
            ->groupBy('chart_accounts.account', 'chart_accounts.name')
            ->orderByRaw('account')
            ->get();

        return response()->json([
            'accounts' => $accounts,
            'company' => $company
        ]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function filter(Request $request)
    {
        return ChartAccount::where([
            ['type', '=', 'persona natural'],
            ['account', 'LIKE', $request->get('filter') . '%'],
        ])->get();
    }

    /**
     * Show data to Legder.
     *
     * @return \Illuminate\Http\Response
     */
    public function ledger()
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);
        $branch = $company->branches->first();

        $accounts = ChartAccount::join('account_entry_items', 'account_entry_items.chart_account_id', 'chart_accounts.id')
            ->join('account_entries', 'account_entry_items.account_entry_id', 'account_entries.id')
            ->select(
                'chart_accounts.account',
                'chart_accounts.name',
                'account_entries.id',
                'account_entries.date',
                'account_entries.description',
                'account_entry_items.debit',
                'account_entry_items.have'
            )
            ->when($branch->id, function ($query, $id) {
                return $query->where('account_entries.branch_id', $id);
            })
            ->orderByRaw('account, account_entries.id')
            ->get();

        $newAccounts = array();
        $i = 0;
        $accountbefore = $accounts[$i]->account;

        while ($i < count($accounts)) {
            if ($i === 0 || $accountbefore !== $accounts[$i]->account) {
                $account = [
                    'account' => $accounts[$i]->account,
                    'name' => $accounts[$i]->name,
                    'accountentryitems' => [
                        [
                            'id' => $accounts[$i]->id,
                            'date' => $accounts[$i]->date,
                            'description' => $accounts[$i]->description,
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
                        'id' => $accounts[$i]->id,
                        'date' => $accounts[$i]->date,
                        'description' => $accounts[$i]->description,
                        'debit' => $accounts[$i]->debit,
                        'have' => $accounts[$i]->have
                    ]
                );
            }

            $accountbefore = $accounts[$i]->account;
            $i++;
        }

        return response()->json([
            'chartAccounts' => $newAccounts,
            'company' => $company
        ]);
    }

    /**
     * Show data to Legder.
     *
     * @return \Illuminate\Http\Response
     */
    public function resultState()
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);

        $accounts = $this->resultStateSql($company);

        return response()->json([
            'resultstates' => $accounts,
            'company' => $company
        ]);
    }

    public function resultStatePdf($level1)
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);

        $charts = $this->resultStateSql($company, $level1);

        $charts = json_encode($charts);
        $charts = json_decode($charts, true);

        $pdf = PDF::loadView('balancepurchase', compact('charts', 'company'));

        $dom_pdf = $pdf->getDomPDF();

        $canvas = $dom_pdf->get_canvas();

        //For Header
        $header = $canvas->open_object();
        $canvas->page_text(30, 20, $company['company'], null, 10, array(0, 0, 0));
        $canvas->page_text(30, 35, "Fecha de impresión: " . date('d/m/Y', strtotime('+5 hours')), null, 8, array(0, 0, 0));

        $canvas->line(30, 55, 560, 55, array(.3, .3, .3), 1);
        $canvas->close_object();
        $canvas->add_object($header, "all");

        //For Footer
        $footer = $canvas->open_object();
        $canvas->line(30, 805, 560, 805, array(.3, .3, .3), 1);
        $canvas->page_text(30, 810, "www.auditwhole.com | Contabilidad en línea.", null, 8, array(0, 0, 0));
        $canvas->page_text(520, 810, "Página {PAGE_NUM} de {PAGE_COUNT}", null, 8, array(0, 0, 0));
        $canvas->close_object();
        $canvas->add_object($footer, "all");

        return $pdf->stream();
    }

    private function resultStateSql($company, $level = null)
    {
        $branch = $company->branches->first();

        // $sql = "SELECT ca.account, ca.name, SUM(aei.debit) AS debit, SUM(aei.have) AS have ";
        $sql = "SELECT ca.account, ca.name, ";
        $sql .= "SUM(CASE WHEN aei.account_entry_id IN (SELECT iae1.id FROM account_entries AS iae1 WHERE iae1.branch_id = $branch->id) THEN aei.debit END) AS debit, ";
        $sql .= "SUM(CASE WHEN aei.account_entry_id IN (SELECT iae2.id FROM account_entries AS iae2 WHERE iae2.branch_id = $branch->id) THEN aei.have END) AS have ";
        $sql .= "FROM chart_accounts AS ca LEFT JOIN account_entry_items AS aei ";
        $sql .= "ON ca.id = aei.chart_account_id ";
        $sql .= "WHERE type = '$company->type' AND (branch_id IS NULL OR branch_id = $branch->id) ";
        $sql .= "AND (account LIKE '4%' OR account LIKE '5%') ";
        $sql .= "GROUP BY ca.account, ca.name ";
        $sql .= "ORDER BY sort_account DESC";

        // Query to database
        $accounts = DB::select($sql);

        // Calculate account
        $accounts = $this->account($accounts, $level, true);

        // Filter the account entry & egrres result array with object Entry & Egres
        $entryandegress = array_filter(
            $accounts,
            function ($account) {
                return $account->account === '4' || $account->account === '5';
            }
        );

        // Sort the keys array because the array_filter return array not sort keys
        $entryandegress = array_values($entryandegress);

        // Add new object with the amount Utilidad o Pérdida
        array_push($accounts, [
            'account' => '',
            'name' => 'Utilidad o Pérdida',
            'debit' => 0,
            'have' => 0,
            'amount' => $entryandegress[0]->amount - $entryandegress[1]->amount,
        ]);

        return $accounts;
    }

    public function balanceSheet()
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);

        $accounts = $this->balanceSheetSql($company);

        return response()->json([
            'balancesheet' => $accounts,
            'company' => $company
        ]);
    }

    public function balanceSheetPdf($level1)
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);

        $charts = $this->balanceSheetSql($company, $level1);

        $charts = json_encode($charts);
        $charts = json_decode($charts, true);

        $pdf = PDF::loadView('balancesheet', compact('charts', 'company'));

        $dom_pdf = $pdf->getDomPDF();

        $canvas = $dom_pdf->get_canvas();

        //For Header
        $header = $canvas->open_object();
        $canvas->page_text(30, 20, $company['company'], null, 10, array(0, 0, 0));
        $canvas->page_text(30, 35, "Fecha de impresión: " . date('d/m/Y', strtotime('+5 hours')), null, 8, array(0, 0, 0));
        $canvas->line(30, 55, 560, 55, array(.3, .3, .3), 1);
        $canvas->close_object();
        $canvas->add_object($header, "all");

        //For Footer
        $footer = $canvas->open_object();
        $canvas->line(30, 805, 560, 805, array(.3, .3, .3), 1);
        $canvas->page_text(30, 810, "www.auditwhole.com | Contabilidad en línea.", null, 8, array(0, 0, 0));
        $canvas->page_text(520, 810, "Página {PAGE_NUM} de {PAGE_COUNT}", null, 8, array(0, 0, 0));
        $canvas->close_object();
        $canvas->add_object($footer, "all");

        return $pdf->stream();
    }

    private function balanceSheetSql($company, $level = null)
    {
        $branch = $company->branches->first();

        // $sql = "SELECT ca.account, ca.name, SUM(aei.debit) AS debit, SUM(aei.have) AS have ";
        $sql = "SELECT ca.account, ca.name, ";
        $sql .= "SUM(CASE WHEN aei.account_entry_id IN (SELECT iae1.id FROM account_entries AS iae1 WHERE iae1.branch_id = $branch->id) THEN aei.debit END) AS debit, ";
        $sql .= "SUM(CASE WHEN aei.account_entry_id IN (SELECT iae2.id FROM account_entries AS iae2 WHERE iae2.branch_id = $branch->id) THEN aei.have END) AS have ";
        $sql .= "FROM chart_accounts AS ca LEFT JOIN account_entry_items AS aei ";
        $sql .= "ON ca.id = aei.chart_account_id ";
        $sql .= "WHERE type = '$company->type' AND (branch_id IS NULL OR branch_id = $branch->id) ";
        //-----Start Modify only ajust---------------
        // $sql .= "AND (account LIKE '1%' OR account LIKE '2%' OR account LIKE '3%') ";
        //-----End Modify only ajust---------------
        $sql .= "GROUP BY ca.account, ca.name ";
        $sql .= "ORDER BY sort_account DESC";

        // Query to database
        $accounts = DB::select($sql);

        // Calculate account
        $accounts = $this->account($accounts, $level, true);

        //-----Start Modify only ajust.......................
        // Filter the account entry & egrres result array with object Entry & Egres
        $entryandegress = array_filter(
            $accounts,
            function ($account) {
                return $account->account === '4' || $account->account === '5';
            }
        );

        // Sort the keys array because the array_filter return array not sort keys
        $entryandegress = array_values($entryandegress);

        $accounts = array_filter(
            $accounts,
            function ($account) {
                return substr($account->account, 0, 1) === '1' || substr($account->account, 0, 1) === '2' || substr($account->account, 0, 1) === '3';
            }
        );

        $accounts = array_values($accounts);
        $accounts[count($accounts) - 1]->amount += ($entryandegress[0]->amount - $entryandegress[1]->amount);
        $accounts[count($accounts) - 2]->amount += ($entryandegress[0]->amount - $entryandegress[1]->amount);
        $accounts[count($accounts) - 5]->amount += ($entryandegress[0]->amount - $entryandegress[1]->amount);
        //-----End Modify only ajust.......................

        return $accounts;
    }

    /**
     * Here is the accounting process.
     *
     * @param  $accounts array inverse
     * @return array reverse
     */
    private function account($accounts, $level = null, $resum = false)
    {
        // Levels account subaccount the most subaccount accept 10
        $levels = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0);

        foreach ($accounts as $chartAccount) {

            switch (substr($chartAccount->account, 0, 1)) {
                case '1': // Accounts Active
                    $chartAccount->amount = $chartAccount->debit - $chartAccount->have;
                    break;
                case '2': // Accounts Pasive
                    $chartAccount->amount = $chartAccount->have - $chartAccount->debit;
                    break;
                case '3': // Accounts Patrimony
                    $chartAccount->amount = $chartAccount->have - $chartAccount->debit;
                    break;
                case '4': // Accounts Entry
                    $chartAccount->amount = $chartAccount->have - $chartAccount->debit;
                    break;
                case '5': // Accounts Egress
                    $chartAccount->amount = $chartAccount->debit - $chartAccount->have;
                    break;
            }

            // Count char('.')
            $pos = substr_count($chartAccount->account, '.');
            // Sum subaccount if exist
            $chartAccount->amount += $levels[$pos + 1];
            // Sum OR subtraction the account before
            // $levels[$pos] += substr($chartAccount->name, 0, 3) === '(-)' ? ((-1) * $chartAccount->amount) : $chartAccount->amount;
            // $levels[$pos] += substr($chartAccount->name, 0, 3) === '(-)' ? ((-1) * $chartAccount->amount) : $chartAccount->amount;
            $levels[$pos] += $chartAccount->amount;
            // Asign 0 to subaccount
            $levels[$pos + 1] = 0;
        }

        //Start reverse account ...........................
        $arr_reverse = array();

        // Inverse the array
        for ($i = count($accounts) - 1; $i >= 0; $i--) {
            if ($level !== null) {
                if ($level > substr_count($accounts[$i]->account, '.')) {
                    array_push($arr_reverse, $accounts[$i]);
                }
            } else {
                array_push($arr_reverse, $accounts[$i]);
            }
        }
        //End reverse account ...........................

        //Start resum .........................
        // if ($resum) {

        //     // Filter the account amount distinct 0 for resum
        //     $arr_reverse = array_filter(
        //         $arr_reverse,
        //         function ($account) {
        //             return $account->amount !== 0;
        //         }
        //     );

        //     // Sort the keys array because the array_filter return array not sort keys
        //     $arr_reverse = array_values($arr_reverse);
        // }
        //End resum .........................

        return $arr_reverse;
    }
}
