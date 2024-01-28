<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\EmisionPoint;
use Illuminate\Http\Request;

class EmisionPointController extends Controller
{
    public function index($branch_id)
    {
        $branch = Branch::find($branch_id);

        $points = EmisionPoint::select('id', 'point', 'invoice', 'creditnote', 'retention', 'referralguide', 'settlementonpurchase', 'recognition')
            ->where('branch_id', $branch_id)->get();

        return response()->json(['branch' => $branch, 'points' => $points]);
    }

    public function store(Request $request)
    {
        $this->validate(
            $request,
            [
                'branch_id' => 'required',
                'point' => 'required',
                'invoice' => 'required',
                'creditnote' => 'required',
                'retention' => 'required',
                'referralguide' => 'required',
                'settlementonpurchase' => 'required',
                'recognition' => 'nullable'
            ]
        );

        EmisionPoint::create($request->all());
    }

    public function update($id, Request $request)
    {
        $emisionPoint = EmisionPoint::find($id);
        $emisionPoint->update($request->all());
    }
}
