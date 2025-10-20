<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResources;
use App\Models\Branch;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\IceCataloge;
use App\Models\IvaTax;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function productlist(Request $request = null)
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

        $products = Product::join('iva_taxes', 'iva_taxes.code', 'products.iva')
            ->leftJoin('categories', 'categories.id', 'category_id')
            ->leftJoin('unities', 'unities.id', 'unity_id')
            ->where('products.branch_id', $branch->id)
            ->where(function ($query) use ($search) {
                return $query->where('products.code', 'LIKE', "%$search%")
                    ->orWhere('products.name', 'LIKE', "%$search%");
            })
            ->selectRaw('products.id,products.code,products.type_product,products.name,products.price1,iva_taxes.code AS iva_code,percentage,products.ice,products.stock,tourism,categories.category,unities.unity')
            ->orderBy('products.created_at', 'DESC');

        return ProductResources::collection($products->paginate($paginate));
    }

    public function create()
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);

        $taxes = IvaTax::where('state', 'active');

        // Si compania no tiene habilitado IVA 5% desabilitar
        if (!$company->base5) {
            $taxes->where('code', '<>', 5);
        }

        return response()->json([
            'ivaTaxes' => $taxes->get(),
            'iceCataloges' => $company->ice ? IceCataloge::all() : [],
        ]);
    }

    public function store(Request $request)
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);
        $branch = Branch::where('company_id', $company->id)
            ->orderBy('created_at')->first();

        $this->validate($request, [
            'code' => [
                'required',
                Rule::unique('products')->where(function ($query) use ($branch) {
                    return $query->where('branch_id', $branch->id);
                }),
            ],
        ], [
            'code.required' => 'El código es obligatorio',
            'code.unique' => 'Ya existe un producto con el código ' . $request->code . ' prueba',
        ]);

        $branch->products()->create($request->all());
        
        // if ($company->inventory && $request->has('stock')) {
        //     $product->inventories()->create([
        //         'quantity' => $request->stock,
        //         'price' => $request->price1,
        //         'type' => 'Inventario inicial',
        //         'code_provider' => null,
        //         'date' => substr(Carbon::today()->toISOString(), 0, 10)
        //     ]);
        // }
    }

    public function import(Request $request)
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);
        $branch = Branch::where('company_id', $company->id)
            ->orderBy('created_at')->first();

        $products = $request->get('products');

        $newProducts = [];
        foreach ($products as $product) {
            array_push($newProducts, [
                'code' => $product['code'],
                'type_product' => $product['type_product'],
                'name' => $product['name'],
                'price1' => $product['price1'],
                'iva' => $product['iva'],
                'stock' => $product['stock'],
            ]);
        }
        $product = Branch::where('company_id', $company->id)
            ->orderBy('created_at')->first()
            ->products()->createMany($newProducts);

        $products = Product::join('iva_taxes', 'iva_taxes.code', 'products.iva')
            ->leftJoin('categories', 'categories.id', 'category_id')
            ->leftJoin('unities', 'unities.id', 'unity_id')
            ->where('products.branch_id', $branch->id)
            ->selectRaw('products.id,products.code,products.type_product,products.name,products.price1,iva_taxes.code AS iva_code,percentage,products.ice,products.stock,tourism,categories.category,unities.unity')
            ->orderBy('products.created_at', 'DESC');

        return ProductResources::collection($products->paginate());
    }

    public function getmasive(Request $request)
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);
        $branch = Branch::where('company_id', $company->id)
            ->orderBy('created_at')->first();

        $prods = $request->get('prods'); // array con code, price, quantity

        $codes = array_column($prods, 'code'); // extrae los códigos directamente

        $orderItems = Product::join('iva_taxes AS it', 'it.code', 'iva')
            ->selectRaw('products.id, products.code, price1 AS price, name, it.code AS iva, it.percentage')
            ->where('branch_id', $branch->id)
            ->whereIn('products.code', $codes)
            ->get()
            ->map(function ($item) use ($prods) {
                // buscar el producto original por code
                $prodRequest = collect($prods)->firstWhere('code', $item->code);

                $price = $prodRequest['price'] ?? $item->price; // fallback al precio original si no está
                $quantity = $prodRequest['quantity'] ?? 1;

                $item->product_id = (int) $item->id;
                $item->price = $price;
                $item->quantity = $quantity;
                $item->discount = 0;
                $item->total_iva = round($quantity * $price, 2);

                return $item;
            });

        return response()->json([
            'orderItems' => $orderItems
        ]);
    }

    function findObjectById($id, $array)
    {
        foreach ($array as $element) {
            if ($id == $element['code']) {
                return [
                    'quantity' => $element['quantity'],
                    'price' => $element['price']
                ];
            }
        }

        return false;
    }

    public function show($id)
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);

        $taxes = IvaTax::where('state', 'active');

        // Si compania no tiene habilitado IVA 5% desabilitar
        if (!$company->base5) {
            $taxes->where('code', '<>', 5);
        }
        return response()->json([
            'product' => Product::find($id),
            'ivaTaxes' => $taxes->get(),
            'iceCataloges' => $company->ice ? IceCataloge::all() : [],
        ]);
    }

    public function edit(Request $request)
    {
        return Product::find($request->get('id'));
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        try {
            $product->update($request->all());
        } catch (\Illuminate\Database\QueryException $e) {
            $errorCode = $e->errorInfo[1];
            if ($errorCode == 1062) {
                return response()->json(['message' => 'KEY_DUPLICATE']);
            }
        }
    }

    public function export()
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);
        $branch = Branch::where('company_id', $company->id)
            ->orderBy('created_at')->first();

        $spreadsheet = new Spreadsheet();
        $activeWorksheet = $spreadsheet->getActiveSheet();
        $activeWorksheet->setCellValue('A1', 'Codigo');
        $activeWorksheet->setCellValue('B1', 'Nombre');
        $activeWorksheet->setCellValue('C1', 'Precio');
        $activeWorksheet->setCellValue('D1', 'IVA');
        $activeWorksheet->setCellValue('E1', 'ICE');

        $products = Product::select('code', 'name', 'price1', 'iva', 'ice')
            ->where('branch_id', $branch->id)
            ->get();

        $row = 2;

        foreach ($products as $product) {
            $activeWorksheet->getCell('A' . $row)->setValueExplicit($product->code, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $activeWorksheet->setCellValue('B' . $row, $product->name);
            $activeWorksheet->setCellValue('C' . $row, $product->price1);
            $activeWorksheet->setCellValue('D' . $row, $product->iva == 2 ? '12%' : '0%');
            $activeWorksheet->setCellValue('E' . $row, $product->ice);
            $row++;
        }

        $filename = Storage::path("productos.xlsx");

        try {
            $writer = new Xlsx($spreadsheet);
            $writer->save($filename);
            $content = file_get_contents($filename);

            unlink($filename);

            return $content;
        } catch (\Exception $e) {
            exit($e->getMessage());
        }

        exit($content);
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);

        if ($product->orderItems->count() > 0 || $product->referralGuideItems->count() > 0 || $product->shopItems->count() > 0) {
            Product::destroy($product->id);
        }

        $product->delete();

        return response()->json(['message' => 'PRODUCT_DELETED']);
    }
}
