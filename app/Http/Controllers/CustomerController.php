<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\Customer;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Http\Resources\CustomerResources;

class CustomerController extends Controller
{
    public function customerlist(Request $request = null)
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);
        $branch = $company->branches->first();

        $search = '';
        $paginate = 15;

        if ($request) {
            $search = $request->search;
            $paginate = $request->has('paginate') ? $request->paginate : $paginate;
        }

        $customers = Customer::where('branch_id', $branch->id)
            ->where(function ($query) use ($search) {
                return $query->where('identication', 'LIKE', "%$search%")
                    ->orWhere('name', 'LIKE', "%$search%");
            })
            ->orderBy('created_at', 'DESC');

        return CustomerResources::collection($customers->paginate($paginate));
    }

    public function store(Request $request)
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);

        try {
            $customer = $company->branches->first()->customers()->create($request->all());
            return response()->json(['customer' => $customer]);
        } catch (\Illuminate\Database\QueryException $e) {
            $errorCode = $e->errorInfo[1];
            if ($errorCode == 1062) {
                return response()->json(['message' => 'KEY_DUPLICATE']);
            }
        }
    }

    public function edit(int $id)
    {
        $customer = Customer::find($id);
        return response()->json(['customer' => $customer]);
    }

    public function update(Request $request, int $id)
    {
        $customer = Customer::findOrFail($id);

        try {
            $customer->update($request->all());
        } catch (\Illuminate\Database\QueryException $e) {
            $errorCode = $e->errorInfo[1];
            if ($errorCode == 1062) {
                return response()->json(['message' => 'KEY_DUPLICATE']);
            }
        }
    }

    public function importCsv(Request $request)
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);
        $branch = $company->branches->first();

        $customers = $request->get('customers');

        $newcustomers = [];
        foreach ($customers as $customer) {
            array_push($newcustomers, [
                'type_identification' => $customer['type_identification'],
                'identication' => $customer['identication'],
                'name' => $customer['name'],
                'address' => $customer['address'],
            ]);
        }
        $customer = $company->branches->first()->customers()->createMany($newcustomers);

        $customers = Customer::where('branch_id', $branch->id);

        return CustomerResources::collection($customers->latest()->paginate());
    }
}
