<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\Response;
use App\Http\Resources\UnityResources;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\Branch;

class UnityController extends Controller
{
    public function index()
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();

        $company = Company::find($level->level_id);
        $branch = Branch::where('company_id', $company->id)
            ->orderBy('created_at')->first();

        return UnityResources::collection($branch->unities);
    }

    public function store(Request $request)
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();

        $company = Company::find($level->level_id);
        $branch = Branch::where('company_id', $company->id)
            ->orderBy('created_at')->first();

        try {
            $unity = $branch->unities()->create($request->all());
        } catch (\Illuminate\Database\QueryException $e) {
            $errorCode = $e->errorInfo[1];
            if ($errorCode == 1062) {
                return response()->json(['message' => 'KEY_DUPLICATE'], 405);
            }
        }

        UnityResources::withoutWrapping();   //Remove collection return one category
        return (new UnityResources($unity))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
