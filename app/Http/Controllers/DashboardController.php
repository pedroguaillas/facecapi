<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Provider;
use App\Models\Shop;

class DashboardController extends Controller
{

    public function index()
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);
        $branch = $company->branches->first();

        $active = $company->active_voucher;
        $expired = $company->expired;

        $orders = DB::table('orders')->select(DB::raw("SUM(total) as ingreso, DATE_FORMAT(date, '%m-%Y') AS name"))
            ->where('branch_id', $branch->id)
            ->groupBy('name')->get();

        $shops = DB::table('shops')->select(DB::raw("SUM(total) as egreso, DATE_FORMAT(date, '%m-%Y') AS name"))
            ->where('branch_id', $branch->id)
            ->groupBy('name')->get();

        $count_orders = Order::where('branch_id', $branch->id)->get();
        $count_shops = Shop::where('branch_id', $branch->id)->get();

        $count_customers = Customer::where('branch_id', $branch->id)->get();
        $count_providers = Provider::where('branch_id', $branch->id)->get();

        return response()->json([
            'active' => $active,
            'expired' => $expired,
            'orders' => $orders,
            'shops' => $shops,
            'count_orders' => $count_orders->count(),
            'count_shops' => $count_shops->count(),
            'count_customers' => $count_customers->count() - 1,
            'count_providers' => $count_providers->count(),
        ]);
    }
}
