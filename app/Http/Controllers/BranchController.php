<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Models\Company;
use App\Models\Branch;

class BranchController extends Controller
{
    public function index()
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);

        return response()->json(['branches' => $company->branches]);
    }

    public function store(Request $request)
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);

        $this->validate($request, [
            'store' => [
                'required',
                Rule::unique('branches')->where(function ($query) use ($company) {
                    return $query->where('company_id', $company->id);
                }),
            ],
            'address' => 'required|min:3|max:300',
        ]);

        $branch = $company->branches()->create($request->except('cf'));

        // Si crear consumidor final
        if ($request->has('cf') && $request->cf) {
            $branch->customers()->create([
                'type_identification' => 'cf',
                'identication' => '9999999999999',
                'name' => 'Consumidor Final'
            ]);
        }
    }

    public function update(Request $request, $id)
    {
        $branch = Branch::find($id);
        $branch->update($request->all());
    }
}
