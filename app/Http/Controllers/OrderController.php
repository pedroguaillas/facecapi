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
        $branch = $company->branches->first();

        $search = $request->search;

        $orders = Order::join('customers AS c', 'c.id', 'customer_id')
            ->select('orders.*', 'c.name', 'c.email')
            ->orderBy('orders.created_at', 'DESC')
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
        // $branch = $company->branches->first();

        $points = Branch::selectRaw("branches.id AS branch_id,LPAD(store,3,'0') AS store,ep.id,LPAD(point,3,'0') AS point,ep.invoice,ep.creditnote,recognition")
            ->leftJoin('emision_points AS ep', 'branches.id', 'branch_id')
            ->where('company_id', $company->id)
            ->get();

        return response()->json([
            'points' => $points,
            // 'series' => $this->getSeries($branch),
            'methodOfPayments' => MethodOfPayment::all(),
            'pay_method' => $company->pay_method
        ]);
    }

    private function getSeries($branch)
    {
        $branch_id = $branch->id;
        $invoice = Order::select('serie')
            ->where([
                ['branch_id', $branch_id], // De la sucursal especifico
                ['voucher_type', 1] // 1-Factura
            ])
            ->orderBy('created_at', 'desc') // Para traer el ultimo
            ->first();

        $cn = Order::select('serie')
            ->where([
                ['branch_id', $branch_id], // De la sucursal especifico
                ['voucher_type', 4] // 4-Nota-Credito
            ])
            ->orderBy('created_at', 'desc') // Para traer el ultimo
            ->first();

        $new_obj = [
            'invoice' => $this->generedSerie($invoice, $branch->store),
            'cn' => $this->generedSerie($cn, $branch->store),
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

        // Nuevo objeto para agregar metodo de pago
        $input = $request->except(['products', 'send', 'aditionals', 'point_id']);

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
                        'ice' => $product['ice'] ?? 0
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
            $emisionPoint = EmisionPoint::find($request->point_id);
            $emisionPoint->{$request->voucher_type == 1 ? 'invoice' : 'creditnote'} = (int)substr($request->serie, 8) + 1;
            $emisionPoint->save();

            if ($request->get('send')) {
                (new OrderXmlController())->xml($order->id);
            }
        }
    }

    public function show($id)
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);
        // $branch = $company->branches->first();

        $points = Branch::selectRaw("branches.id AS branch_id,LPAD(store,3,'0') AS store,ep.id,LPAD(point,3,'0') AS point,ep.invoice,ep.creditnote,recognition")
            ->leftJoin('emision_points AS ep', 'branches.id', 'branch_id')
            ->where('company_id', $company->id)
            ->get();

        $order = Order::findOrFail($id);

        $products = Product::join('order_items AS oi', 'product_id', 'products.id')
            ->select('products.*')
            ->where('order_id', $order->id)
            ->get();

        $orderitems = Product::join('order_items AS oi', 'product_id', 'products.id')
            ->select('products.iva', 'products.ice AS codice', 'oi.*')
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
            // 'series' => $this->getSeries($branch),
            'methodOfPayments' => MethodOfPayment::all()
        ]);
    }

    public function showPdf($id)
    {
        $movement = Order::join('customers AS c', 'c.id', 'customer_id')
            ->select('orders.*', 'c.*')
            ->where('orders.id', $id)
            ->first();

        $movement_items = OrderItem::join('products', 'products.id', 'product_id')
            ->select('products.*', 'order_items.*')
            ->where('order_id', $id)
            ->get();

        $orderaditionals = OrderAditional::where('order_id', $id)->get();

        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);

        switch ($movement->voucher_type) {
            case 1:
                $payMethod = MethodOfPayment::where('code', $movement->pay_method)->first()->description;
                $pdf = Pdf::loadView('vouchers/invoice', compact('movement', 'company', 'movement_items', 'orderaditionals', 'payMethod'));
                break;
            case 4:
                $pdf = PDF::loadView('vouchers/creditnote', compact('movement', 'company', 'movement_items', 'orderaditionals'));
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

        $movement_items = OrderItem::join('products', 'products.id', 'order_items.product_id')
            ->select('products.*', 'order_items.*')
            ->where('order_items.order_id', $id)
            ->get();

        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);

        $pdf = PDF::loadView('vouchers/printf', compact('movement', 'company', 'movement_items'));
        $pdf->setPaper(array(0, 0, (8 / 2.54) * 72, (10 / 2.54) * 72), 'portrait');

        return $pdf->stream();
    }

    public function generatePdf($id)
    {
        $movement = Order::join('customers AS c', 'orders.customer_id', 'c.id')
            ->select('orders.*', 'c.*')
            ->where('orders.id', $id)
            ->first();

        $movement_items = OrderItem::join('products', 'products.id', 'order_items.product_id')
            ->select('products.*', 'order_items.*')
            ->where('order_items.order_id', $id)
            ->get();

        $orderaditionals = OrderAditional::where('order_id', $id)->get();

        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);

        switch ($movement->voucher_type) {
            case 1:
                $payMethod = MethodOfPayment::where('code', $movement->pay_method)->first()->description;
                $pdf = PDF::loadView('vouchers/invoice', compact('movement', 'company', 'movement_items', 'orderaditionals', 'payMethod'));
                break;
            case 4:
                $pdf = PDF::loadView('vouchers/creditnote', compact('movement', 'company', 'movement_items', 'orderaditionals'));
                break;
        }

        $pdf->save(Storage::path(str_replace('.xml', '.pdf', $movement->xml)));
    }

    public function update(Request $request, $id)
    {
        $order = Order::findOrFail($id);

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
                        'ice' => $product['ice'] ?? 0
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
        $branch = $company->branches->first();

        $year = substr($month, 0, 4);
        $month = substr($month, 5, 2);

        $spreadsheet = new Spreadsheet();
        $activeWorksheet = $spreadsheet->getActiveSheet();
        $activeWorksheet->setCellValue('A1', 'Identificación');
        $activeWorksheet->setCellValue('B1', 'Cliente');
        $activeWorksheet->setCellValue('C1', 'Comprobante');
        $activeWorksheet->setCellValue('D1', 'Fecha');
        $activeWorksheet->setCellValue('E1', 'Autorización');
        $activeWorksheet->setCellValue('F1', 'N° de comprobante');
        $activeWorksheet->setCellValue('G1', 'No IVA');
        $activeWorksheet->setCellValue('H1', 'No grabada');
        $activeWorksheet->setCellValue('I1', 'Grabada');
        $activeWorksheet->setCellValue('J1', 'IVA');
        $activeWorksheet->setCellValue('K1', 'Total');
        $activeWorksheet->setCellValue('L1', 'Estado');

        $orders = Order::join('customers AS c', 'c.id', 'customer_id')
            ->select('voucher_type', 'date', 'authorization', 'serie', 'no_iva', 'base0', 'base12', 'iva', 'total', 'orders.state', 'identication', 'name')
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->where('orders.branch_id', $branch->id)
            ->get();

        $row = 2;

        foreach ($orders as $order) {
            $activeWorksheet->getCell('A' . $row)->setValueExplicit($order->identication, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $activeWorksheet->setCellValue('B' . $row, $order->name);
            $activeWorksheet->setCellValue('C' . $row,  $this->vtconvertion($order->voucher_type));
            $activeWorksheet->setCellValue('D' . $row, $order->date);
            $activeWorksheet->getCell('E' . $row)->setValueExplicit($order->authorization, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $activeWorksheet->setCellValue('F' . $row, $order->serie);
            $activeWorksheet->setCellValue('G' . $row, $order->no_iva);
            $activeWorksheet->setCellValue('H' . $row, $order->base0);
            $activeWorksheet->setCellValue('I' . $row, $order->base12);
            $activeWorksheet->setCellValue('J' . $row, $order->iva);
            $activeWorksheet->setCellValue('K' . $row, $order->total);
            $activeWorksheet->setCellValue('L' . $row, $order->state);
            $row++;
        }

        $filename = Storage::path("ventas.xlsx");

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
