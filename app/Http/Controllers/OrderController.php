<?php

namespace App\Http\Controllers;

use App\Models\{Company, Customer, Branch, MethodOfPayment, Order, OrderAditional, OrderItem, Product};
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
use App\Services\{OrderStoreService, OrderPdfService};

class OrderController extends Controller
{
    protected $orderStoreService;
    protected $orderPdfService;

    public function __construct(OrderStoreService $orderStoreService,OrderPdfService $orderPdfService)
    {
        $this->orderStoreService = $orderStoreService;
        $this->orderPdfService = $orderPdfService;
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
            'voucher_type' => 'required|integer|in:1,4',
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

    public function generatePdf($id)
    {
        $this->orderPdfService->savePdf($id);
    }

    public function showPdf($id)
    {
        [$pdf] = $this->orderPdfService->buildPdf($id);
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
