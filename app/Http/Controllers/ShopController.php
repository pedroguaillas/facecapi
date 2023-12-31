<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Http\Resources\ProductResources;
use App\Http\Resources\ProviderResources;
use App\Http\Resources\ShopResources;
use App\Models\ShopRetentionItem;
use App\Models\Product;
use App\Models\Provider;
use App\Models\Shop;
use App\Models\ShopItem;
use App\Models\Tax;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ShopController extends Controller
{
    public function shoplist(Request $request)
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);
        $branch = $company->branches->first();

        $search = $request->search;

        $shops = Shop::join('providers AS p', 'p.id', 'provider_id')
            ->select('shops.*', 'p.name', 'p.email')
            ->where('shops.branch_id', $branch->id)
            ->where(function ($query) use ($search) {
                return $query->where('shops.serie', 'LIKE', "%$search%")
                    ->orWhere('p.name', 'LIKE', "%$search%");
            })
            ->orderBy('shops.created_at', 'DESC');

        return ShopResources::collection($shops->paginate());
    }

    public function create()
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);
        $branch = $company->branches->first();

        return response()->json([
            'taxes' => Tax::all(),
            'series' => $this->getSeries($branch)
        ]);
    }

    private function getSeries($branch)
    {
        $branch_id = $branch->id;

        $set_purchase = Shop::select('serie')
            ->where([
                ['branch_id', $branch_id], // De la sucursal específico
                ['voucher_type', 3] // 3-Liquidacion-de-compra
            ])
            // ->whereIn('state', ['AUTORIZADO', 'ANULADO'])
            ->orderBy('created_at', 'desc') // Para traer el ultimo
            ->first();

        $retention = Shop::select('serie_retencion AS serie')
            ->where('branch_id', $branch_id) // De la sucursal específico
            // ->whereIn('state_retencion', ['AUTORIZADO', 'ANULADO'])
            ->orderBy('created_at', 'desc') // Para traer el ultimo
            ->first();

        $new_obj = [
            'set_purchase' => $this->generedSerie($set_purchase, $branch->store),
            'retention' => $this->generedSerie($retention, $branch->store)
        ];

        return $new_obj;
    }

    //Return the serie of sales generated
    private function generedSerie($serie, $branch_store)
    {
        if ($serie != null) {
            $serie = $serie->serie;
            //Convert string to array
            $serie = explode("-", $serie);
            //Get value Integer from String & sum 1
            $serie[2] = (int) $serie[2] + 1;
            //Complete 9 zeros to left 
            $serie[2] = str_pad($serie[2], 9, 0, STR_PAD_LEFT);
            //convert Array to String
            $serie = implode("-", $serie);
        } else {
            $serie = str_pad($branch_store, 3, 0, STR_PAD_LEFT) . '-010-000000001';
        }

        return $serie;
    }

    public function store(Request $request)
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);
        $branch = $company->branches->first();

        $except = ['taxes', 'pay_methods', 'app_retention', 'send'];

        // Inicio ... Validar que se anule la retencion anterior
        $shop_verfiy = Shop::where([
            ['branch_id', $branch->id],
            ['serie', $request->serie],
            ['state_retencion', 'AUTORIZADO'],
            ['authorization', $request->authorization],
            ['provider_id', $request->provider_id]
        ])->get();

        if (Count($shop_verfiy)) {
            return response()->json(['message' => 'RETENTION_EMITIDA'], 405);
        }
        // Fin ... Validar que se anule la retencion anterior

        if ($shop = $branch->shops()->create($request->except($except))) {

            $send_set = false;

            if (count($request->get('products')) > 0) {

                $products = $request->get('products');
                $array = [];

                foreach ($products as $product) {
                    $array[] = [
                        'product_id' => $product['product_id'],
                        'quantity' => $product['quantity'],
                        'price' => $product['price'],
                        'discount' => $product['discount']
                    ];
                }

                $shop->shopitems()->createMany($array);

                // Verificando que sea una LIQUIDACIÓN EN COMPRA para enviar
                if ($request->get('send') && $shop->voucher_type === 3) {
                    $send_set = true;
                }
            }

            $send_ret = false;

            // Verificando que sea una LIQUIDACIÓN EN COMPRA o FACTURA, además que exista retenciones
            if ($request->get('app_retention') && count($request->get('taxes')) > 0) {

                $taxes = $request->get('taxes');
                $array = [];

                foreach ($taxes as $tax) {
                    $array[] = [
                        'code' => $tax['code'],
                        'tax_code' => $tax['tax_code'],
                        'base' => $tax['base'],
                        'porcentage' => $tax['porcentage'],
                        'value' => $tax['value']
                    ];
                }

                $shop->shopretentionitems()->createMany($array);

                if ($request->get('send')) {
                    $send_ret = true;
                }
            }

            // Envio de comprobantes
            if ($send_set) {
                (new SettlementOnPurchaseXmlController())->xml($shop->id);
            }
            if ($send_ret) {
                (new RetentionXmlController())->xml($shop->id);
            }
        }
    }

    public function show($id)
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);
        $branch = $company->branches->first();

        $shop = Shop::findOrFail($id);

        $products = Product::join('shop_items AS si', 'product_id', 'products.id')
            ->select('products.*')
            ->where('shop_id', $id)
            ->get();

        $shopitems = Product::join('shop_items AS si', 'si.product_id', 'products.id')
            ->select('products.iva', 'si.*')
            ->where('shop_id', $shop->id)
            ->get();

        $series = $this->getSeries($branch);
        $shop->serie_retencion = ($shop->serie_retencion !== null) ? $shop->serie_retencion : $series['retention'];

        $providers = Provider::where('id', $shop->provider_id)->get();

        return response()->json([
            'products' => ProductResources::collection($products),
            'providers' => ProviderResources::collection($providers),
            'shop' => $shop,
            'shopitems' => $shopitems,
            'shopretentionitems' => $shop->shopretentionitems,
            'taxes' => Tax::all(),
            'series' => $series
        ]);
    }

    // Solo liquidacion en compra
    public function showPdf($id)
    {
        $movement = Shop::join('providers AS p', 'shops.provider_id', 'p.id')
            ->select('shops.*', 'p.*')
            ->where('shops.id', $id)
            ->first();

        $movement_items = ShopItem::join('products', 'products.id', 'shop_items.product_id')
            ->select('products.*', 'shop_items.*')
            ->where('shop_items.shop_id', $id)
            ->get();

        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);

        $pdf = PDF::loadView('vouchers/settlementonpurchase', compact('movement', 'company', 'movement_items'));

        return $pdf->stream();
    }

    public function showPdfRetention($id)
    {
        $movement = Shop::join('providers AS p', 'provider_id', 'p.id')
            ->select(
                'shops.id',
                'shops.date AS date_v',
                'shops.voucher_type AS voucher_type_v',
                'shops.date_retention AS date',
                'shops.serie AS serie_retencion',
                'shops.serie_retencion AS serie',
                'shops.autorized_retention AS autorized',
                'shops.xml_retention AS xml',
                'shops.authorization_retention AS authorization',
                'p.name',
                'p.identication'
            )
            ->where('shops.id', $id)
            ->first();

        $movement->voucher_type = 7;

        $retention_items = $movement->shopretentionitems;

        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);

        $comprobante = $this->voucherType($movement->voucher_type_v);

        $pdf = PDF::loadView('vouchers/retention', compact('movement', 'company', 'retention_items', 'comprobante'));

        return $pdf->stream();
    }

    public function generatePdfRetention($id)
    {
        $movement = Shop::join('providers AS p', 'provider_id', 'p.id')
            ->select(
                'shops.id',
                'shops.date AS date_v',
                'shops.voucher_type AS voucher_type_v',
                'shops.date_retention AS date',
                'shops.serie AS serie_retencion',
                'shops.serie_retencion AS serie',
                'shops.autorized_retention AS autorized',
                'shops.xml_retention AS xml',
                'shops.authorization_retention AS authorization',
                'p.name',
                'p.identication'
            )
            ->where('shops.id', $id)
            ->first();

        $movement->voucher_type = 7;

        $retention_items = $movement->shopretentionitems;

        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);

        $comprobante = $this->voucherType($movement->voucher_type_v);

        $pdf = PDF::loadView('vouchers/retention', compact('movement', 'company', 'retention_items', 'comprobante'));

        $pdf->save(Storage::path(str_replace('.xml', '.pdf', $movement->xml)));
    }

    public function voucherType(int $tv)
    {
        switch ($tv) {
            case 1:
                return "Factura";
            case 2:
                return "Nota Venta";
            case 3:
                return "Liquidación en Compra";
            case 5:
                return "Nota de Débito";
        }
    }

    public function update(Request $request, $id)
    {
        $except = ['id', 'taxes', 'pay_methods', 'app_retention', 'send'];

        $shop = Shop::find($id);

        if ($shop->update($request->except($except))) {

            if ($shop->voucher_type < 4 && $request->get('app_retention') && count($request->get('taxes')) > 0) {

                $taxes = $request->get('taxes');
                $array = [];

                foreach ($taxes as $tax) {
                    $array[] = [
                        'code' => $tax['code'],
                        'tax_code' => $tax['tax_code'],
                        'base' => $tax['base'],
                        'porcentage' => $tax['porcentage'],
                        'value' => $tax['value']
                    ];
                }

                ShopRetentionItem::where('shop_id', $shop->id)->delete();

                $shop->shopretentionitems()->createMany($array);

                if ($request->get('send') && $shop->autorized_retention === null) {
                    (new RetentionXmlController())->xml($shop->id);
                }
            }
        }
    }

    public function export($month)
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);
        $branch = $company->branches->first();

        $year = substr($month, 0, 4);
        $month = substr($month, 5, 2);

        $spreadsheet = new Spreadsheet();
        $activeWorksheet = $spreadsheet->getActiveSheet();
        $activeWorksheet->setCellValue('A1', 'Identificación');
        $activeWorksheet->setCellValue('B1', 'Proveedor');
        $activeWorksheet->setCellValue('C1', 'Comprobante');
        $activeWorksheet->setCellValue('D1', 'Fecha');
        $activeWorksheet->setCellValue('E1', 'Autorización');
        $activeWorksheet->setCellValue('F1', 'N° de comprobante');
        $activeWorksheet->setCellValue('G1', 'No IVA');
        $activeWorksheet->setCellValue('H1', 'No grabada');
        $activeWorksheet->setCellValue('I1', 'Grabada');
        $activeWorksheet->setCellValue('J1', 'IVA');
        $activeWorksheet->setCellValue('K1', 'Total');
        $activeWorksheet->setCellValue('L1', 'Estado L/C');

        $shops = DB::table('shops AS s')
            ->join('providers AS p', 'p.id', 'provider_id')
            ->select('s.voucher_type', 's.date', 's.authorization', 's.serie', 's.no_iva', 's.base0', 's.base12', 's.iva', 's.total', 's.state', 'p.identication', 'p.name')
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->where('s.branch_id', $branch->id)
            ->get();

        $row = 2;

        foreach ($shops as $shop) {
            $activeWorksheet->getCell('A' . $row)->setValueExplicit($shop->identication, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $activeWorksheet->setCellValue('B' . $row, $shop->name);
            $activeWorksheet->setCellValue('C' . $row, $this->vtconvertion($shop->voucher_type));
            $activeWorksheet->setCellValue('D' . $row, $shop->date);
            $activeWorksheet->getCell('E' . $row)->setValueExplicit($shop->authorization, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $activeWorksheet->setCellValue('F' . $row, $shop->serie);
            $activeWorksheet->setCellValue('G' . $row, $shop->no_iva);
            $activeWorksheet->setCellValue('H' . $row, $shop->base0);
            $activeWorksheet->setCellValue('I' . $row, $shop->base12);
            $activeWorksheet->setCellValue('J' . $row, $shop->iva);
            $activeWorksheet->setCellValue('K' . $row, $shop->total);
            $activeWorksheet->setCellValue('L' . $row, $shop->state);
            $row++;
        }

        $filename = Storage::path("compras.xlsx");

        try {
            $writer = new Xlsx($spreadsheet);
            $writer->save($filename);
            $content = file_get_contents($filename);

            unlink($filename);

            return $content;
        } catch (Exception $e) {
            exit($e->getMessage());
        }

        exit($content);
    }

    private function vtconvertion($type)
    {
        switch ($type) {
            case 1:
                return 'Factura';
            case 2:
                return 'Nota de venta';
            case 3:
                return 'Liquidación en compra';
            case 4:
                return 'Nota de crédito';
        }
    }
}
