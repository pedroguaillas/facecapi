<?php

namespace App\Http\Controllers;

use App\Models\Carrier;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Http\Resources\CarrierResources;

class CarrierController extends Controller
{
    public function carrierlist(Request $request = null)
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

        $carriers = Carrier::where('branch_id', $branch->id)
            ->where(function ($query) use ($search) {
                return $query->where('identication', 'LIKE', "%$search%")
                    ->orWhere('name', 'LIKE', "%$search%");
            })
            ->orderBy('created_at', 'DESC');

        return CarrierResources::collection($carriers->paginate($paginate));
    }

    public function store(Request $request)
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);

        try {
            $carrier = $company->branches->first()->carriers()->create($request->all());
            return response()->json(['carrier' => $carrier]);
        } catch (\Illuminate\Database\QueryException $e) {
            $errorCode = $e->errorInfo[1];
            if ($errorCode == 1062) {
                return response()->json(['message' => 'KEY_DUPLICATE']);
            }
        }
    }

    public function edit(int $id)
    {
        $carrier = Carrier::find($id);
        return response()->json(['carrier' => $carrier]);
    }

    public function update(Request $request, int $id)
    {
        $carrier = Carrier::findOrFail($id);

        try {
            $carrier->update($request->all());
        } catch (\Illuminate\Database\QueryException $e) {
            $errorCode = $e->errorInfo[1];
            if ($errorCode == 1062) {
                return response()->json(['message' => 'KEY_DUPLICATE']);
            }
        }
    }
}
