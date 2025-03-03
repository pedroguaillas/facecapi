<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Customer;
use App\Http\Resources\CustomerResources;
use App\Http\Resources\OrderResources;
use App\Http\Resources\ProductResources;
use App\Models\Branch;
use App\Models\EmisionPoint;
use App\Models\MethodOfPayment;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderAditional;
use App\Models\OrderItem;
use App\Models\Product;
use App\StaticClasses\VoucherStates;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class OrderController extends Controller
{
    public function orderlist(Request $request)
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);
        $branch = Branch::where('company_id', $company->id)
            ->orderBy('created_at')->first();

        $search = $request->search;

        $orders = Order::join('customers AS c', 'c.id', 'customer_id')
            ->select('orders.*', 'c.name', 'c.email')
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
            'methodOfPayments' => MethodOfPayment::all(),
            'pay_method' => $company->pay_method,
            'tourism' => $tourism,
        ]);
    }

    public function store(Request $request)
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);
        $branch = Branch::where('company_id', $company->id)
            ->orderBy('created_at')->first();

        // Nuevo objeto para agregar metodo de pago
        $input = $request->except(['products', 'send', 'aditionals', 'point_id']);

        if (array_key_exists("guia", $input) && trim($input['guia']) === '') {
            $input['guia'] = null;
        }

        // Extraer la serie RECIVIDA en este formato 001-001-
        $serie = substr($request->serie, 0, 8);
        $emisionPoint = EmisionPoint::find($request->point_id);
        // Evitar secuencía duplicada
        $serie .= str_pad($emisionPoint->{$request->voucher_type == 1 ? 'invoice' : 'creditnote'}, 9, "0", STR_PAD_LEFT);
        // Modifica la nueva serie
        $input = [...$input, 'serie' => $serie];

        if ($order = $branch->orders()->create($input)) {

            // Registro de los Items de la Orden
            $products = $request->get('products');

            if (count($products) > 0) {
                $array = [];

                // Si tiene habilitado inventario
                $array_inventory = [];

                foreach ($products as $product) {
                    $array[] = [
                        'product_id' => $product['product_id'],
                        'quantity' => $product['quantity'],
                        'price' => $product['price'],
                        'discount' => $product['discount'],
                        'ice' => $product['ice'] ?? 0,
                        'iva' => $product['iva'],
                    ];

                    // Si tiene habilitado inventario
                    if ($company->inventory) {
                        $array_inventory[] = [
                            'product_id' => $product['product_id'],
                            'quantity' => $product['quantity'],
                            'price' => $product['price'],
                            'type' => 'Venta',
                            'date' => substr(Carbon::today()->toISOString(), 0, 10)
                        ];
                    }
                }
                $order->orderitems()->createMany($array);

                // Si tiene habilitado inventario
                if ($company->inventory) {
                    $order->inventories()->createMany($array_inventory);
                }
            }

            // Registro de la Informacion Adicional de la Orden
            $aditionals = $request->get('aditionals');

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

            // Actualizar secuencia del comprobante
            $emisionPoint->{$request->voucher_type == 1 ? 'invoice' : 'creditnote'}++;
            $emisionPoint->save();

            if ($request->get('send')) {
                (new OrderXmlController())->xml($order->id);
                (new WSSriOrderController())->send($order->id);
            }
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

        $order = Order::findOrFail($id);

        $products = Product::join('order_items AS oi', 'product_id', 'products.id')
            ->select('products.*')
            ->where('order_id', $order->id)
            ->get();

        $orderitems = OrderItem::join('products', 'product_id', 'products.id')
            ->join('iva_taxes AS it', 'it.code', 'order_items.iva')
            ->selectRaw('quantity,price,discount,order_items.ice,products.ice AS codice,product_id,it.code AS iva,it.percentage')
            ->where('order_id', $order->id)
            ->get();

        $orderaditionals = OrderAditional::where('order_id', $order->id)->get();

        $customers = Customer::where('id', $order->customer_id)->get();

        return response()->json([
            'products' => ProductResources::collection($products),
            'customers' => CustomerResources::collection($customers),
            'order' => $order,
            'order_items' => $orderitems,
            'order_aditionals' => $orderaditionals,
            'points' => $points,
            'methodOfPayments' => MethodOfPayment::all()
        ]);
    }

    public function showPdf($id)
    {
        $movement = Order::join('customers AS c', 'c.id', 'customer_id')
            ->select('orders.*', 'c.identication', 'c.name', 'c.address')
            ->where('orders.id', $id)
            ->first();

        $after = false;
        $dateToCheck = Carbon::parse($movement->voucher_type == 4 ? $movement->date_order : $movement->date);

        if ($dateToCheck->isBefore(Carbon::parse('2024-04-01'))) {
            $after = true;
        }

        $movement_items = OrderItem::join('products', 'products.id', 'product_id')
            ->select('products.*', 'order_items.*')
            ->where('order_id', $id)
            ->get();

        $orderaditionals = OrderAditional::where('order_id', $id)->get();

        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);

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
                $pdf = Pdf::loadView('vouchers/invoice', compact('movement', 'company', 'branch', 'movement_items', 'orderaditionals', 'payMethod', 'after'));
                break;
            case 4:
                $pdf = PDF::loadView('vouchers/creditnote', compact('movement', 'company', 'branch', 'movement_items', 'orderaditionals', 'after'));
                break;
        }

        return $pdf->stream();
    }

    // PDF tamaño pequeño a imprimir
    public function printfPdf($id)
    {
        $movement = Order::join('customers AS c', 'orders.customer_id', 'c.id')
            ->select('orders.*', 'c.*')
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

    public function generatePdf($id)
    {
        $movement = Order::join('customers AS c', 'orders.customer_id', 'c.id')
            ->select('orders.*', 'c.*')
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

        $orderaditionals = OrderAditional::where('order_id', $id)->get();

        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);

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
                $pdf = PDF::loadView('vouchers/invoice', compact('movement', 'company', 'branch', 'movement_items', 'orderaditionals', 'payMethod', 'after'));
                break;
            case 4:
                $pdf = PDF::loadView('vouchers/creditnote', compact('movement', 'company', 'branch', 'movement_items', 'orderaditionals'));
                break;
        }

        $pdf->save(Storage::path(str_replace('.xml', '.pdf', $movement->xml)));
    }

    public function update(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        if ($order->state === VoucherStates::AUTHORIZED)
            return;

        if ($order->update($request->except(['id', 'products', 'send', 'aditionals']))) {

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
