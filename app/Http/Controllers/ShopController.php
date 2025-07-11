<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Http\Resources\ProductResources;
use App\Http\Resources\ProviderResources;
use App\Http\Resources\ShopResources;
use App\Models\Branch;
use App\Models\EmisionPoint;
use App\Models\ShopRetentionItem;
use App\Models\Product;
use App\Models\Provider;
use App\Models\Shop;
use App\Models\ShopItem;
use App\Models\Tax;
use App\StaticClasses\VoucherStates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
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

        $branch = Branch::where('company_id', $company->id)
            ->orderBy('created_at')->first();

        $search = $request->search;

        $shops = Shop::join('providers AS p', 'p.id', 'provider_id')
            ->leftJoin('shop_retention_items', 'shops.id', 'shop_id')
            ->selectRaw("shops.id,DATE_FORMAT(shops.date, '%d-%m-%Y') as date,voucher_type,serie,total,serie_retencion,state_retencion,xml_retention,send_mail_retention,extra_detail_retention,shops.state,xml,extra_detail,p.name,p.email,SUM(shop_retention_items.value) AS retention")
            ->where('shops.branch_id', $branch->id)
            ->where(function ($query) use ($search) {
                return $query->where('shops.serie', 'LIKE', "%$search%")
                    ->orWhere('p.name', 'LIKE', "%$search%");
            })
            ->groupBy('shops.id', 'date', 'voucher_type', 'serie', 'total', 'serie_retencion', 'state_retencion', 'xml_retention', 'send_mail_retention', 'extra_detail_retention', 'shops.state', 'xml', 'extra_detail', 'p.name', 'p.email')
            ->orderBy('shops.created_at', 'DESC');

        return ShopResources::collection($shops->paginate());
    }

    public function create()
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);

        $points = Branch::selectRaw("branches.id AS branch_id,LPAD(store,3,'0') AS store,ep.id,LPAD(point,3,'0') AS point,ep.retention,ep.settlementonpurchase,recognition")
            ->leftJoin('emision_points AS ep', 'branches.id', 'branch_id')
            ->where('company_id', $company->id)
            ->get();

        return response()->json([
            'taxes' => Tax::all(),
            'points' => $points
        ]);
    }

    public function store(Request $request)
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);
        $branch = Branch::where('company_id', $company->id)
            ->orderBy('created_at')->first();

        // Inicio ... Validar que se anule la retencion anterior

        $exists = Shop::where([
            ['branch_id', $branch->id],
            ['serie', $request->serie],
            ['state_retencion', 'AUTORIZADO'],
            ['authorization', $request->authorization],
            ['provider_id', $request->provider_id]
        ])->exists();

        if ($exists) {
            return response()->json(['message' => 'RETENTION_EMITIDA'], 422);
        }
        // Fin ... Validar que se anule la retencion anterior

        $except = ['taxes', 'pay_methods', 'app_retention', 'send', 'point_id'];
        $inputs = $request->except($except);

        // Validar existencia del punto de emisión
        $emisionPoint = EmisionPoint::findOrFail($request->point_id);

        // Serie para comprobante
        if ((int) $request->voucher_type === 3) {
            $serieBase = substr($request->serie, 0, 8);
            $secuencial = str_pad((int) $emisionPoint->settlementonpurchase, 9, "0", STR_PAD_LEFT);
            $inputs['serie'] = $serieBase . $secuencial;
            $inputs['state'] = 'CREADO';
        }

        // Serie para retención
        $taxes = $request->get('taxes');
        if ($request->boolean('app_retention') && is_array($taxes) && count($taxes) > 0) {
            $serieBaseRet = substr($request->serie_retencion, 0, 8);
            $secuencialRet = str_pad((int) $emisionPoint->retention, 9, "0", STR_PAD_LEFT);
            $inputs['serie_retencion'] = $serieBaseRet . $secuencialRet;
            $inputs['state_retencion'] = 'CREADO';
        }

        if ($shop = $branch->shops()->create($inputs)) {

            $send_set = false;

            if (count($request->get('products')) > 0) {

                $products = $request->get('products');
                $array = [];

                foreach ($products as $product) {
                    $array[] = [
                        'product_id' => $product['product_id'],
                        'quantity' => $product['quantity'],
                        'price' => $product['price'],
                        'discount' => $product['discount'],
                        'iva' => $product['iva'],
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

            // Si aplica retención es liquidación en compra
            if ($request->has('point_id')) {
                $emisionPoint = EmisionPoint::find($request->point_id);
                if ((int) $request->voucher_type === 3)
                    $emisionPoint->settlementonpurchase += 1;
                if ($request->app_retention)
                    $emisionPoint->retention += 1;
                $emisionPoint->save();
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
        $shop = Shop::find($id);

        $filteredShop = collect($shop->toArray())
            ->filter(function ($value) {
                return !is_null($value);
            })
            ->all();

        $products = Product::join('shop_items AS si', 'product_id', 'products.id')
            ->select('products.*')
            ->where('shop_id', $id)
            ->get();

        $shopitems = ShopItem::join('products AS p', 'product_id', 'p.id')
            ->select('p.name', 'shop_items.*')
            ->where('shop_id', $shop->id)
            ->get();

        $shopRetentionItems = ShopRetentionItem::select('shop_retention_items.*', 'taxes.conception as tax_name')
            ->join('taxes', 'taxes.code', 'shop_retention_items.tax_code')
            ->where('shop_id', $shop->id)->get();

        $providers = Provider::where('id', $shop->provider_id)->get();

        return response()->json([
            'products' => ProductResources::collection($products),
            'providers' => ProviderResources::collection($providers),
            'shop' => $filteredShop,
            'shopitems' => $shopitems,
            'shopretentionitems' => $shopRetentionItems,
            'taxes' => Tax::all(),
        ]);
    }

    // Solo liquidacion en compra
    public function showPdf($id)
    {
        $movement = Shop::join('providers AS p', 'provider_id', 'p.id')
            ->select('shops.*', 'p.identication', 'p.name', 'p.address')
            ->where('shops.id', $id)
            ->first();

        $after = false;
        $dateToCheck = Carbon::parse($movement->date);

        if ($dateToCheck->isBefore(Carbon::parse('2024-04-01'))) {
            $after = true;
        }

        $movement_items = ShopItem::join('products', 'products.id', 'product_id')
            ->select('products.*', 'shop_items.*')
            ->where('shop_id', $id)
            ->get();

        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);
        $company->logo_dir = $company->logo_dir ?: 'default.png';

        $branch = Branch::where([
            'company_id' => $company->id,
            'store' => (int) substr($movement->serie, 0, 3),
        ])->get();

        if ($branch->count() === 0) {
            $branch = Branch::where('company_id', $company->id)
                ->orderBy('created_at')->first();
        } elseif ($branch->count() === 1) {
            $branch = $branch->first();
        }

        $pdf = PDF::loadView('vouchers/settlementonpurchase', compact('movement', 'branch', 'company', 'movement_items', 'after'));

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
        $company->logo_dir = $company->logo_dir ?: 'default.png';

        $branch = Branch::where([
            'company_id' => $company->id,
            'store' => (int) substr($movement->serie, 0, 3),
        ])->get();

        if ($branch->count() === 0) {
            $branch = Branch::where('company_id', $company->id)
                ->orderBy('created_at')->first();
        } elseif ($branch->count() === 1) {
            $branch = $branch->first();
        }

        $comprobante = $this->voucherType($movement->voucher_type_v);

        $pdf = PDF::loadView('vouchers/retention', compact('movement', 'company', 'branch', 'retention_items', 'comprobante'));

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
        $company->logo_dir = $company->logo_dir ?: 'default.png';

        $branch = Branch::where([
            'company_id' => $company->id,
            'store' => (int) substr($movement->serie, 0, 3),
        ])->get();

        if ($branch->count() === 0) {
            $branch = Branch::where('company_id', $company->id)
                ->orderBy('created_at')->first();
        } elseif ($branch->count() === 1) {
            $branch = $branch->first();
        }

        $comprobante = $this->voucherType($movement->voucher_type_v);

        $pdf = PDF::loadView('vouchers/retention', compact('movement', 'company', 'branch', 'retention_items', 'comprobante'));

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
        $shop = Shop::find($id);

        if ($shop->state === VoucherStates::AUTHORIZED || $shop->state_retencion === VoucherStates::AUTHORIZED)
            return;

        $except = ['id', 'taxes', 'pay_methods', 'app_retention', 'send'];

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
        $branch = Branch::where('company_id', $company->id)
            ->orderBy('created_at')->first();

        $after = false;
        $dateToCheck = Carbon::parse($month . '-01');

        if ($dateToCheck->isBefore(Carbon::parse('2024-04-01'))) {
            $after = true;
        }

        $year = substr($month, 0, 4);
        $month = substr($month, 5, 2);

        $columns = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N');
        $col = 0;

        $spreadsheet = new Spreadsheet();
        $activeWorksheet = $spreadsheet->getActiveSheet();
        $activeWorksheet->setCellValue($columns[$col++] . '1', 'Identificación');
        $activeWorksheet->setCellValue($columns[$col++] . '1', 'Proveedor');
        $activeWorksheet->setCellValue($columns[$col++] . '1', 'Comprobante');
        $activeWorksheet->setCellValue($columns[$col++] . '1', 'Fecha');
        $activeWorksheet->setCellValue($columns[$col++] . '1', 'Autorización');
        $activeWorksheet->setCellValue($columns[$col++] . '1', 'N° de comprobante');
        $activeWorksheet->setCellValue($columns[$col++] . '1', 'No IVA');
        $activeWorksheet->setCellValue($columns[$col++] . '1', 'No grabada');
        if (!$after) {
            $activeWorksheet->setCellValue($columns[$col++] . '1', 'Gra 5%');
        }
        $activeWorksheet->setCellValue($columns[$col++] . '1', 'Gra ' . ($after ? '12%' : '15%'));
        if (!$after) {
            $activeWorksheet->setCellValue($columns[$col++] . '1', 'IVA 5%');
        }
        $activeWorksheet->setCellValue($columns[$col++] . '1', 'IVA ' . ($after ? '12%' : '15%'));
        $activeWorksheet->setCellValue($columns[$col++] . '1', 'Total');
        $activeWorksheet->setCellValue($columns[$col++] . '1', 'Estado L/C');

        $shops = Shop::join('providers AS p', 'p.id', 'provider_id')
            ->select('voucher_type', 'date', 'authorization', 'serie', 'no_iva', 'base0', 'base5', ($after ? 'base12' : 'base15'), 'iva5', ($after ? 'iva' : 'iva15'), 'total', 'shops.state', 'p.identication', 'p.name')
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->where('shops.branch_id', $branch->id)
            ->get();

        $row = 2;

        foreach ($shops as $shop) {
            $col = 0;
            $activeWorksheet->getCell($columns[$col++] . $row)->setValueExplicit($shop->identication, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $activeWorksheet->setCellValue($columns[$col++] . $row, $shop->name);
            $activeWorksheet->setCellValue($columns[$col++] . $row, $this->vtconvertion($shop->voucher_type));
            $activeWorksheet->setCellValue($columns[$col++] . $row, $shop->date);
            $activeWorksheet->getCell($columns[$col++] . $row)->setValueExplicit($shop->authorization, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $activeWorksheet->setCellValue($columns[$col++] . $row, $shop->serie);
            $activeWorksheet->setCellValue($columns[$col++] . $row, $shop->no_iva);
            $activeWorksheet->setCellValue($columns[$col++] . $row, $shop->base0);
            if (!$after) {
                $activeWorksheet->setCellValue($columns[$col++] . $row, $shop->base5);
            }
            $activeWorksheet->setCellValue($columns[$col++] . $row, $shop->{$after ? 'base12' : 'base15'});
            if (!$after) {
                $activeWorksheet->setCellValue($columns[$col++] . $row, $shop->iva5);
            }
            $activeWorksheet->setCellValue($columns[$col++] . $row, $shop->{$after ? 'iva' : 'iva15'});
            $activeWorksheet->setCellValue($columns[$col++] . $row, $shop->total);
            $activeWorksheet->setCellValue($columns[$col++] . $row, $shop->state);
            $row++;
        }

        $filename = Storage::path("compras.xlsx");

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
