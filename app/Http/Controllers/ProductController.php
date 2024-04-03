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
            ->selectRaw('products.id,products.code,products.type_product,products.name,products.price1,iva_taxes.code AS iva_code,percentage,products.ice,products.stock,categories.category,unities.unity')
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
            'iceCataloges' => IceCataloge::all(),
        ]);
    }

    public function store(Request $request)
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);
        $branch = Branch::where('company_id', $company->id)
            ->orderBy('created_at')->first();

        try {
            $product = $branch->products()->create($request->all());
            if ($company->inventory && $request->has('stock')) {
                $product->inventories()->create([
                    'quantity' => $request->stock,
                    'price' => $request->price1,
                    'type' => 'Inventario inicial',
                    'code_provider' => null,
                    'date' => substr(Carbon::today()->toISOString(), 0, 10)
                ]);
            }
        } catch (\Illuminate\Database\QueryException $e) {
            $errorCode = $e->errorInfo[1];
            if ($errorCode == 1062) {
                return response()->json(['message' => 'KEY_DUPLICATE']);
            }
        }
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
                'unity_id' => strlen($product['unity_id']) ? $product['unity_id'] : null,
                'price1' => $product['price1'],
                'price2' => strlen($product['price2']) ? $product['price2'] : null,
                'price3' => strlen($product['price3']) ? $product['price3'] : null,
                'iva' => $product['iva']
            ]);
        }
        $product = Branch::where('company_id', $company->id)
            ->orderBy('created_at')->first()
            ->products()->createMany($newProducts);

        $products = Product::leftJoin('categories', 'categories.id', 'products.category_id')
            ->leftJoin('unities', 'unities.id', 'products.unity_id')
            ->where('products.branch_id', $branch->id)
            ->select('products.*', 'categories.category', 'unities.unity');

        return ProductResources::collection($products->latest()->paginate());
    }

    public function getmasive(Request $request)
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);
        $branch = Branch::where('company_id', $company->id)
            ->orderBy('created_at')->first();

        $prods = $request->get('prods');

        $products = Product::select('id', 'code', 'name', 'price1', 'iva')
            ->where('branch_id', $branch->id)
            ->whereIn('code', $this->toArrayCodes($prods))
            ->get();

        $order_items = [];

        foreach ($products as $product) {
            $prod = $this->findObjectById($product->code, $prods);
            $price = $prod['price'] ?? floatval($product->price1);
            $product->price1 = $price;
            array_push($order_items, [
                'product_id' => $product->id,
                'discount' => 0,
                'iva' => $product->iva,
                'price' => $price,
                'quantity' => $prod['quantity'],
                'total_iva' => $price * $prod['quantity']
            ]);
        }

        return response()->json([
            'products' => $products,
            'order_items' => $order_items
        ]);
    }

    function toArrayCodes($objs)
    {
        $codes = array();
        foreach ($objs as $obj) {
            array_push($codes, $obj['code']);
        }
        return $codes;
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
            'iceCataloges' => IceCataloge::all(),
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
}
