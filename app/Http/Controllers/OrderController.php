<?php

namespace App\Http\Controllers;

use App\Models\{Company, Customer, Branch, MethodOfPayment, Order, OrderAditional, OrderItem, Product, Repayment};
use App\Http\Resources\{CustomerResources, OrderResources, ProductResources};
use Illuminate\Http\Request;
use App\StaticClasses\VoucherStates;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Http\JsonResponse;
use App\Services\OrderStoreService;

class OrderController extends Controller
{
    protected $orderStoreService;

    public function __construct(OrderStoreService $orderStoreService)
    {
        $this->orderStoreService = $orderStoreService;
    }
    
    public function orderlist(Request $request)
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);
        $branch = Branch::where('company_id', $company->id)
            ->orderBy('created_at')->first();

        $search = $request->search;

        $orders = Order::join('customers AS c', 'c.id', 'customer_id')
            ->select(
                'orders.*',
                'c.name',
                'c.email',
                \DB::raw("DATE_FORMAT(orders.date, '%d-%m-%Y') as date")
            )
            ->orderBy('orders.id', 'DESC')
            ->where('orders.branch_id', $branch->id)
            ->where(function ($query) use ($search) {
                return $query->where('orders.serie', 'LIKE', "%$search%")
                    ->orWhere('c.name', 'LIKE', "%$search%");
            });

        if ($request->has('date')) {
            $orders->whereDate('date', $request->date);
        }

        return OrderResources::collection($orders->paginate());
    }

    public function create()
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);

        $points = Branch::selectRaw("branches.id AS branch_id,LPAD(store,3,'0') AS store,ep.id,LPAD(point,3,'0') AS point,ep.invoice,ep.creditnote,recognition")
            ->leftJoin('emision_points AS ep', 'branches.id', 'branch_id')
            ->where('company_id', $company->id)
            ->get();

        // Turistas que tienen que facturar con el 8%
        $tourism = false;
        $now = Carbon::now();

        if ($company->base8 && $company->tourism_from !== null && $company->tourism_to !== null) {
            if ($now->isAfter($company->tourism_from) && $now->isBefore($company->tourism_to)) {
                $tourism = true;
            }
        }

        return response()->json([
            'points' => $points,
            'methodOfPayments' => MethodOfPayment::whereNotIN('code', ['15', '17', '18', '21'])->get(),
            'pay_method' => $company->pay_method,
            'tourism' => $tourism,
            'repayment' => $company->repayment,
        ]);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'customer_id' => 'required|integer|exists:customers,id',
            'total' => 'required|numeric',
            'voucher_type' => 'required|integer|in:1,2',
            'point_id' => 'required|integer|exists:emision_points,id',
        ], [
            'customer_id.required' => 'Debe seleccionar un cliente',
        ]);

        try {
            $order = $this->orderStoreService->createOrder($request->all());

            return new JsonResponse([
                'message' => 'Venta creada con éxito.',
                'data' => $order
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }
    // public function store(Request $request)
    // {
    //     $this->validate($request, [
    //         'customer_id' => 'required|integer|exists:customers,id',
    //     ], [
    //         'customer_id.required' => 'Debe seleccionar un cliente',
    //     ]);

    //     $customer = Customer::find($request->customer_id);

    //     if ($customer && $customer->identication === '9999999999999' && $request->total > 50) {
    //         return response()->json([
    //             'customer_id' => ['No es posible una venta mayor a $50 a consumidor final.']
    //         ], 422);
    //     }

    //     $auth = Auth::user();
    //     $level = $auth->companyusers->first();
    //     $company = Company::find($level->level_id);
    //     $branch = Branch::where('company_id', $company->id)
    //         ->orderBy('created_at')->first();

    //     $input = $request->except(['products', 'send', 'aditionals', 'point_id']);

    //     if (array_key_exists("guia", $input) && trim($input['guia']) === '') {
    //         $input['guia'] = null;
    //     }

    //     // Extraer la serie RECIVIDA en este formato 001-001-
    //     $serie = substr($request->serie, 0, 8);
    //     $emisionPoint = EmisionPoint::find($request->point_id);
    //     // Evitar secuencía duplicada
    //     $serie .= str_pad($emisionPoint->{$request->voucher_type == 1 ? 'invoice' : 'creditnote'}, 9, "0", STR_PAD_LEFT);
    //     // Modifica la nueva serie
    //     $input = [...$input, 'serie' => $serie];

    //     if ($order = $branch->orders()->create($input)) {

    //         // Registro de los Items de la Orden
    //         $products = $request->get('products');

    //         if (count($products) > 0) {
    //             $array = [];

    //             // Si tiene habilitado inventario
    //             $array_inventory = [];

    //             foreach ($products as $product) {
    //                 $array[] = [
    //                     'product_id' => $product['product_id'],
    //                     'quantity' => $product['quantity'],
    //                     'price' => $product['price'],
    //                     'discount' => $product['discount'],
    //                     'ice' => $product['ice'] ?? 0,
    //                     'iva' => $product['iva'],
    //                 ];

    //                 // Si tiene habilitado inventario
    //                 if ($company->inventory) {
    //                     $array_inventory[] = [
    //                         'product_id' => $product['product_id'],
    //                         'quantity' => $product['quantity'],
    //                         'price' => $product['price'],
    //                         'type' => 'Venta',
    //                         'date' => substr(Carbon::today()->toISOString(), 0, 10)
    //                     ];
    //                 }
    //             }
    //             $order->orderitems()->createMany($array);

    //             // Si tiene habilitado inventario
    //             if ($company->inventory) {
    //                 $order->inventories()->createMany($array_inventory);
    //             }
    //         }

    //         // Actualizar secuencia del comprobante
    //         $emisionPoint->{$request->voucher_type == 1 ? 'invoice' : 'creditnote'}++;
    //         $emisionPoint->save();

    //         // Registro de la Informacion Adicional de la Orden
    //         $aditionals = $request->get('aditionals');

    //         if (count($aditionals) > 0) {
    //             $array = [];
    //             foreach ($aditionals as $aditional) {
    //                 if (($aditional['name'] !== null && $aditional['name'] !== '') && ($aditional['description'] !== null && $aditional['description'] !== '')) {
    //                     $array[] = [
    //                         'name' => $aditional['name'],
    //                         'description' => $aditional['description']
    //                     ];
    //                 }
    //             }

    //             if (count($array)) {
    //                 $order->orderaditionals()->createMany($array);
    //             }
    //         }

    //         // Envio al SRI
    //         if ($request->get('send')) {
    //             (new OrderXmlController())->xml($order->id);
    //         }
    //     }

    //     return new JsonResponse([
    //         'message' => 'Venta creada con éxito.',
    //         'data' => $order
    //     ], 201);
    // }

    public function show($id)
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);

        $points = Branch::selectRaw("branches.id AS branch_id,LPAD(store,3,'0') AS store,ep.id,LPAD(point,3,'0') AS point,ep.invoice,ep.creditnote,recognition")
            ->leftJoin('emision_points AS ep', 'branches.id', 'branch_id')
            ->where('company_id', $company->id)
            ->get();

        $order = Order::find($id);

        $filteredOrder = collect($order->toArray())
            ->filter(function ($value) {
                return !is_null($value);
            })
            ->all();

        $products = Product::join('order_items AS oi', 'product_id', 'products.id')
            ->select('products.*')
            ->where('order_id', $order->id)
            ->get();

        $orderitems = OrderItem::join('products', 'product_id', 'products.id')
            ->join('iva_taxes AS it', 'it.code', 'order_items.iva')
            ->selectRaw('order_items.id,quantity,price,discount,order_items.ice,products.name,products.ice AS codice,product_id,it.code AS iva,it.percentage')
            ->where('order_id', $order->id)
            ->get()
            ->map(function ($item) {
                $item->total_iva = round(($item->quantity * $item->price) - $item->discount, 2);
                // Si codice es null, eliminamos ice
                if (is_null($item->codice)) {
                    unset($item->ice);
                    unset($item->codice);
                }
                return $item;
            });

        $orderaditionals = OrderAditional::select('id', 'name', 'description')
            ->where('order_id', $order->id)
            ->get();

        $customers = Customer::where('id', $order->customer_id)->get();

        return response()->json([
            'products' => ProductResources::collection($products),
            'customers' => CustomerResources::collection($customers),
            'order' => $filteredOrder,
            'order_items' => $orderitems,
            'order_aditionals' => $orderaditionals,
            'points' => $points,
            'methodOfPayments' => MethodOfPayment::whereNotIN('code', ['15', '17', '18', '21'])->get()
        ]);
    }

    protected function buildPdf(int $id)
    {
        $movement = Order::join('customers AS c', 'c.id', 'customer_id')
            ->select('orders.*', 'c.identication', 'c.name', 'c.address', 'c.email')
            ->where('orders.id', $id)
            ->firstOrFail();

        $after = false;
        $dateToCheck = Carbon::parse($movement->voucher_type == 4 ? $movement->date_order : $movement->date);

        if ($dateToCheck->isBefore(Carbon::parse('2024-04-01'))) {
            $after = true;
        }

        $movement_items = OrderItem::join('products', 'products.id', 'product_id')
            ->select('products.*', 'order_items.*')
            ->where('order_id', $id)
            ->get();

        $enabledDiscount = $movement_items->contains(fn($item) => $item->discount > 0);

        $orderaditionals = OrderAditional::where('order_id', $id)->get();

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

        switch ($movement->voucher_type) {
            case 1:
                $payMethod = MethodOfPayment::where('code', $movement->pay_method)->first()->description;
                $repayments = Repayment::selectRaw('identification, sequential, date, SUM(base) AS base, SUM(iva) AS iva')
                    ->join('repayment_taxes AS rt', 'repayments.id', 'repayment_id')
                    ->groupBy('identification', 'sequential', 'date')
                    ->where('order_id', $id)
                    ->get();

                $pdf = Pdf::loadView(
                    'vouchers/invoice', 
                    compact('company', 'branch', 'movement', 'movement_items', 'orderaditionals', 'after', 'enabledDiscount', 'payMethod', 'repayments')
                );
                break;
            case 4:
                $pdf = Pdf::loadView(
                    'vouchers/creditnote', 
                    compact('company', 'branch', 'movement', 'movement_items', 'orderaditionals', 'after', 'enabledDiscount')
                );
                break;
            default:
                throw new \Exception("Tipo de comprobante no soportado");
        }

        return [$pdf, $movement];
    }

    public function generatePdf($id)
    {
        [$pdf, $movement] = $this->buildPdf($id);
        $pdf->save(Storage::path(str_replace('.xml', '.pdf', $movement->xml)));
    }

    public function showPdf($id)
    {
        [$pdf] = $this->buildPdf($id);
        return $pdf->stream();
    }

    // PDF tamaño pequeño a imprimir
    public function printfPdf($id)
    {
        $movement = Order::join('customers AS c', 'orders.customer_id', 'c.id')
            ->select(
                'orders.*',
                \DB::raw("DATE_FORMAT(orders.date, '%d-%m-%Y') as date"),
                'c.*'
            )
            ->where('orders.id', $id)
            ->first();

        $after = false;
        $dateToCheck = Carbon::parse($movement->date);

        if ($dateToCheck->isBefore(Carbon::parse('2024-04-01'))) {
            $after = true;
        }

        $movement_items = OrderItem::join('products', 'products.id', 'order_items.product_id')
            ->select('products.*', 'order_items.*')
            ->where('order_items.order_id', $id)
            ->get();

        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);

        $pdf = PDF::loadView('vouchers/printf', compact('movement', 'company', 'movement_items', 'after'));
        $pdf->setPaper(array(0, 0, (8 / 2.54) * 72, (($movement_items->count() > 3 ? 12 : 10) / 2.54) * 72), 'portrait');

        return $pdf->stream();
    }

    public function update(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        if ($order->state === VoucherStates::AUTHORIZED || $order->state === VoucherStates::CANCELED)
            return;

        if ($order->update([
            ...$request->except(['id', 'products', 'send', 'aditionals', 'serie']), 
            'state' => VoucherStates::SAVED
            ])) {

            // Actualizar los Items de la Orden
            $products = $request->get('products');

            if (count($products) > 0) {
                $array = [];
                foreach ($products as $product) {
                    $array[] = [
                        'product_id' => $product['product_id'],
                        'quantity' => $product['quantity'],
                        'price' => $product['price'],
                        'discount' => $product['discount'],
                        'ice' => $product['ice'] ?? 0,
                        'iva' => $product['iva']
                    ];
                }
                OrderItem::where('order_id', $order->id)->delete();
                $order->orderitems()->createMany($array);
            }

            // Actualizar la Informacion Adicional de la Orden
            $aditionals = $request->get('aditionals');

            // Eliminar todos los registros de informacion adicional
            OrderAditional::where('order_id', $order->id)->delete();

            if (count($aditionals) > 0) {
                $array = [];

                foreach ($aditionals as $aditional) {
                    if (($aditional['name'] !== null && $aditional['name'] !== '') && ($aditional['description'] !== null && $aditional['description'] !== '')) {
                        $array[] = [
                            'name' => $aditional['name'],
                            'description' => $aditional['description']
                        ];
                    }
                }

                if (count($array)) {
                    $order->orderaditionals()->createMany($array);
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
        $activeWorksheet->setCellValue($columns[$col++] . '1', 'Cliente');
        $activeWorksheet->setCellValue($columns[$col++] . '1', 'Comprobante');
        $activeWorksheet->setCellValue($columns[$col++] . '1', 'Fecha');
        $activeWorksheet->setCellValue($columns[$col++] . '1', 'Autorización');
        $activeWorksheet->setCellValue($columns[$col++] . '1', 'N° de comprobante');
        $activeWorksheet->setCellValue($columns[$col++] . '1', 'No IVA');
        $activeWorksheet->setCellValue($columns[$col++] . '1', 'No grabada');
        if (!$after && $company->base5) {
            $activeWorksheet->setCellValue($columns[$col++] . '1', 'Gra 5%');
        }
        $activeWorksheet->setCellValue($columns[$col++] . '1', 'Gra ' . ($after ? '12%' : '15%'));
        if (!$after && $company->base5) {
            $activeWorksheet->setCellValue($columns[$col++] . '1', 'IVA 5%');
        }
        $activeWorksheet->setCellValue($columns[$col++] . '1', 'IVA ' . ($after ? ' 12%' : ' 15%'));
        $activeWorksheet->setCellValue($columns[$col++] . '1', 'Total');
        $activeWorksheet->setCellValue($columns[$col++] . '1', 'Estado');

        $orders = Order::join('customers AS c', 'c.id', 'customer_id')
            ->select('voucher_type', 'date', 'authorization', 'serie', 'no_iva', 'base0', 'base5', ($after ? 'base12' : 'base15'), 'iva5', ($after ? 'iva' : 'iva15'), 'total', 'orders.state', 'identication', 'name')
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->where('orders.branch_id', $branch->id)
            ->get();

        $row = 2;

        foreach ($orders as $order) {
            $col = 0;
            $activeWorksheet->getCell($columns[$col++] . $row)->setValueExplicit($order->identication, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $activeWorksheet->setCellValue($columns[$col++] . $row, $order->name);
            $activeWorksheet->setCellValue($columns[$col++] . $row, $this->vtconvertion($order->voucher_type));
            $activeWorksheet->setCellValue($columns[$col++] . $row, $order->date);
            $activeWorksheet->getCell($columns[$col++] . $row)->setValueExplicit($order->authorization, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $activeWorksheet->setCellValue($columns[$col++] . $row, $order->serie);
            $activeWorksheet->setCellValue($columns[$col++] . $row, $order->no_iva);
            $activeWorksheet->setCellValue($columns[$col++] . $row, $order->base0);
            if (!$after && $company->base5) {
                $activeWorksheet->setCellValue($columns[$col++] . $row, $order->base5);
            }
            $activeWorksheet->setCellValue($columns[$col++] . $row, $order->{$after ? 'base12' : 'base15'});
            if (!$after && $company->base5) {
                $activeWorksheet->setCellValue($columns[$col++] . $row, $order->iva5);
            }
            $activeWorksheet->setCellValue($columns[$col++] . $row, $after ? $order->iva : $order->iva15);
            $activeWorksheet->setCellValue($columns[$col++] . $row, $order->total);
            $activeWorksheet->setCellValue($columns[$col++] . $row, $order->state);
            $row++;
        }

        $filename = Storage::path("ventas.xlsx");

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
