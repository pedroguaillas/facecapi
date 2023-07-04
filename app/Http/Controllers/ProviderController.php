<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Http\Resources\ProviderResources;
use App\Models\Provider;

class ProviderController extends Controller
{
    public function providerlist(Request $request = null)
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

        $providers = Provider::where('branch_id', $branch->id)
            ->where(function ($query) use ($search) {
                return $query->where('identication', 'LIKE', "%$search%")
                    ->orWhere('name', 'LIKE', "%$search%");
            })
            ->orderBy('created_at', 'DESC');

        return ProviderResources::collection($providers->paginate($paginate));
    }

    public function store(Request $request)
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);
        $branch = $company->branches->first();

        try {
            $provider = $branch->providers()->create($request->all());
            return response()->json(['provider' => $provider]);
        } catch (\Illuminate\Database\QueryException $e) {
            $errorCode = $e->errorInfo[1];
            if ($errorCode == 1062) {
                $provider = Provider::where([
                    'identication' => $request->identication,
                    'branch_id' => $branch->id,
                ])->get()->first();
                return response()->json([
                    'message' => 'KEY_DUPLICATE',
                    'provider' => $provider
                ]);
            }
        }
    }

    public function edit($id)
    {
        $provider = Provider::findOrFail($id);
        return response()->json(['provider' => $provider]);
    }

    public function update(Request $request, $id)
    {
        $provider = Provider::findOrFail($id);

        try {
            $provider->update($request->except(['id']));
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

        $providers = $request->get('providers');

        $newproviders = [];
        foreach ($providers as $provider) {
            array_push($newproviders, [
                'type_identification' => $provider['type_identification'],
                'identication' => $provider['identication'],
                'name' => $provider['name'],
                'address' => $provider['address'],
                'phone' => $provider['phone'] == '' ? null : $provider['phone'],
                'email' => $provider['email'] == '' ? null : $provider['email']
            ]);
        }
        $provider = $company->branches->first()->providers()->createMany($newproviders);

        $providers = Provider::where('branch_id', $branch->id);

        return providerResources::collection($providers->latest()->paginate());
    }
}
