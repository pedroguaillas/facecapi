<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\Response;
use App\Http\Resources\CategoryResources;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Company;
use App\Models\Branch;

class CategoryController extends Controller
{
    public function index()
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();

        $company = Company::find($level->level_id);
        $branch = Branch::where('company_id', $company->id)->first();

        return CategoryResources::collection($branch->categories);
    }

    public function store(Request $request)
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();

        $company = Company::find($level->level_id);

        try {
            $category = Branch::where('company_id', $company->id)
                ->orderBy('created_at')->first()
                ->categories()->create($request->all());
        } catch (\Illuminate\Database\QueryException $e) {
            $errorCode = $e->errorInfo[1];
            if ($errorCode == 1062) {
                return response()->json(['message' => 'KEY_DUPLICATE'], 405);
            }
        }

        CategoryResources::withoutWrapping();   //Remove collection return one category
        return (new CategoryResources($category))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function show($id)
    {
        CategoryResources::withoutWrapping();
        return (new CategoryResources(Category::find($id)));
    }

    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);
        $category->update($request->all());
        CategoryResources::withoutWrapping();

        return (new CategoryResources($category))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        $category->delete();
        return response([], Response::HTTP_NO_CONTENT);
    }
}
