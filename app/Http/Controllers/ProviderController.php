<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Http\Resources\ProviderResources;
use Illuminate\Support\Facades\Http;
use App\Models\Branch;
use App\Models\Provider;

class ProviderController extends Controller
{
    public function providerlist(Request $request = null)
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);
        $branch = Branch::where('company_id', $company->id)
            ->orderBy('created_at')->first();

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
        $branch = Branch::where('company_id', $company->id)
            ->orderBy('created_at')->first();

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

    public function searchByRuc(string $identification)
    {
        $result = null;
        // Consultar en todo el sistema
        $providers = Provider::select('id', 'branch_id', 'name', 'address', 'phone', 'email')
            ->where([
                'identication' => $identification,
                'type_identification' => 'ruc',
            ])
            ->orderBy('created_at', 'DESC')
            ->get();

        // Si existe registros
        if ($providers->count()) {
            $result = $providers->first();

            $auth = Auth::user();
            $level = $auth->companyusers->first();
            $company = Company::find($level->level_id);
            $branch = Branch::where('company_id', $company->id)
                ->orderBy('created_at')->first();

            // recorrer para buscar tal vez pertenezca a la empresa
            $enc = false;

            foreach ($providers as $provider) {
                if ($provider->branch_id === $branch->id) {
                    // retornar con la empresa o sino retornar el primero
                    $enc = true;
                    $result = $provider;
                }
            }
            if (!$enc) {
                $result->branch_id = 0;
            }
        } else {
            // Si no existe registros en el sistema consultar en la API
            $response = Http::get('http://nessoftfact-001-site6.atempurl.com/api/ConsultasDatosSri/RucSri', [
                'Ruc' => $identification,
                'Apikey' => env('END_POINT_API_Key'),
            ]);

            if ($response['razonSocial'] === null) {
                return;
            } {
                // Ajustar la respuesta
                $result = [
                    'branch_id' => 0,
                    'name' => $response['razonSocial'],
                    'address' => $response['establecimientos'][0]['direccionCompleta'],
                ];
            }
        }

        return response()->json(['provider' => $result]);
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
        $branch = Branch::where('company_id', $company->id)
            ->orderBy('created_at')->first();

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
        $provider = Branch::where('company_id', $company->id)
            ->orderBy('created_at')->first()
            ->providers()->createMany($newproviders);

        $providers = Provider::where('branch_id', $branch->id);

        return providerResources::collection($providers->latest()->paginate());
    }
}
