<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
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

        $orders = Order::selectRaw("SUM(total) as ingreso, DATE_FORMAT(date, '%m-%Y') AS name, DATE_FORMAT(date, '%Y%m') AS orden")
            ->where('branch_id', $branch->id)
            ->groupBy('name', 'orden')
            ->orderBy('orden', 'desc')
            ->take(5)->get();

        $shops = Shop::selectRaw("SUM(total) as egreso, DATE_FORMAT(date, '%m-%Y') AS name, DATE_FORMAT(date, '%Y%m') AS orden")
            ->where('branch_id', $branch->id)
            ->groupBy('name', 'orden')
            ->orderBy('orden', 'desc')
            ->take(5)->get();

        $count_orders = Order::where('branch_id', $branch->id)->count();
        $count_shops = Shop::where('branch_id', $branch->id)->count();

        $count_customers = Customer::where(['branch_id' => $branch->id])
            ->where('type_identification', '<>', 'cf')->count();
        $count_providers = Provider::where('branch_id', $branch->id)->count();

        return response()->json([
            'active' => $active,
            'expired' => $expired,
            'orders' => $orders,
            'shops' => $shops,
            'count_orders' => $count_orders,
            'count_shops' => $count_shops,
            'count_customers' => $count_customers,
            'count_providers' => $count_providers,
        ]);
    }
}
