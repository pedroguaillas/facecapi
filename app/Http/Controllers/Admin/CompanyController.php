<?php

namespace App\Http\Controllers\Admin;

use App\Models\Company;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request; // Este es el correcto, no Illuminate\Http\Client\Request
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        $companies = Company::when($search, function ($query, $search) {
            $query->where('ruc', 'LIKE', "%$search%")
                ->orWhere('company', 'LIKE', "%$search%");
        })
            ->orderBy('id', 'DESC')
            ->paginate(15);

        return $companies;
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'ruc' => 'required|unique:companies,ruc',
            'user' => 'required|unique:users,user',
        ]);
        // New Object constraint
        $input = $request->except(['user', 'password', 'mail']);

        $input['enviroment_type'] = 2;
        $input['decimal'] = 6;
        $input['economic_activity'] = 'otros';

        $company = Company::create($input);
        $user = $request->only(['user', 'password', 'email']);
        $user['user_type_id'] = 2;
        $user['password'] = Hash::make($user['password']);

        $user = User::create($user);

        $user->companyusers()->create([
            'level' => 'owner',
            'level_id' => $company->id
        ]);

        return response()->json([
            'user' => $user
        ]);
    }

    public function searchByRuc(string $identification)
    {
        $response = Http::get('https://srienlinea.sri.gob.ec/sri-catastro-sujeto-servicio-internet/rest/Persona/obtenerPorTipoIdentificacion', [
            'numeroIdentificacion' => $identification,
            'tipoIdentificacion' => 'R'
        ]);

        if ($response->getStatusCode() === 200) {
            // Ajustar la respuesta
            return response()->json([
                'company' => trim($response['nombreCompleto']),
            ]);
        }
    }
}
