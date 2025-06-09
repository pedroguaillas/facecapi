<?php

namespace App\Http\Controllers\Admin;

use App\Models\Company;
use App\Http\Controllers\Controller;

class CustomerController extends Controller
{
    public function index()
    {
        $companies = Company::paginate(15);

        return response()->json(['companies' => $companies]);
    }
}
