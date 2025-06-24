<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Branch;
use App\Models\EmisionPoint;
use App\Models\Lot;
use Illuminate\Http\Request;
use App\Models\Product;
use App\StaticClasses\VoucherStates;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;

class OrderLotController extends Controller
{
    public function create()
    {
        // Si tiene muchos puntos de emision seleccionar uno
    }

    public function store(Request $request)
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);
        $branch = Branch::where('company_id', $company->id)
            ->orderBy('created_at')->first();

        $identifications = [];
        $codes = [];
        $excelData = $this->getData($request);

        $all = true;
        $limit = 0;
        foreach ($excelData as $item) {
            if ($item[0] === null || $item[2] === null || $item[3] === null || $item[4] === null)
                $all = false;
            if ($item[0])
                $identifications[] = $item[0];
            if ($item[2])
                $codes[] = $item[2];
            $limit++;
        }

        if (!$limit > 50) {
            return response()->json(['msm' => 'Limite maximo permite 50 registros']);
        }

        if (!$all) {
            return response()->json(['msm' => 'Hay celdas en blanco']);
        }

        // Quitar repetidos
        $identifications = array_unique($identifications);
        $codes = array_unique($codes);

        $customers = Customer::select('id', 'identication')
            ->whereIn('identication', $identifications)
            ->where('branch_id', $branch->id)->get();

        if (count($identifications) > $customers->count()) {
            return response()->json(['msm' => 'No esta registrado todos los clientes']);
        }

        $products = Product::select('products.id', 'products.code', 'iva_taxes.percentage', 'iva_taxes.code AS iva')
            ->join('iva_taxes', 'products.iva', 'iva_taxes.code')
            ->whereIn('products.code', $codes)
            ->where('products.branch_id', $branch->id)->get();

        if (count($codes) > $products->count()) {
            return response()->json(['msm' => 'No esta registrado todos los productos']);
        }

        // Conversion
        $customers = json_decode(json_encode($customers));
        $products = json_decode(json_encode($products));

        // Actualizar secuencia del comprobante
        $emisionPoint = EmisionPoint::where('branch_id', $branch->id)->first();

        $serie = str_pad($branch->store, 3, '0', STR_PAD_LEFT) . '-' . str_pad($emisionPoint->point, 3, '0', STR_PAD_LEFT) . '-' . str_pad($emisionPoint->lot, 9, '0', STR_PAD_LEFT);
        $date = Carbon::now();
        $authorization = $date->format('dmY') . '01' .
            $company->ruc . $company->enviroment_type . str_replace('-', '', $serie)
            . '123456781';

        $lot = Lot::create([
            'emision_point_id' => $emisionPoint->point,
            'serie' => $serie,
            'authorization' => '' . $authorization . (new OrderXmlController())->generaDigitoModulo11($authorization),
            'state' => VoucherStates::SAVED,
        ]);
        $date = $date->toDateString();
        $emisionPoint->lot++;
        $emisionPoint->save();

        $length = count($excelData);
        $orders = [];
        $orderItems = [];

        for ($i = 0; $i < $length; $i++) {

            $productData = $excelData[$i];

            $customer = array_values(array_filter($customers, function ($item) use ($productData) {
                return $item->identication === $productData[0];
            }));

            $product = array_values(array_filter($products, function ($item) use ($productData) {
                return $item->code === $productData[2];
            }));

            $subTotal = $productData[3] * $productData[4];

            $product = $product[0];
            $iva = $subTotal * $product->percentage * 0.01;

            $input = [
                'date' => $date,
                'sub_total' => $subTotal,
                'serie' => str_pad($branch->store, 3, '0', STR_PAD_LEFT) . '-' . str_pad($emisionPoint->point, 3, '0', STR_PAD_LEFT) . '-' . str_pad($emisionPoint->invoice, 9, '0', STR_PAD_LEFT),
                'customer_id' => $customer[0]->id,
                'lot_id' => $lot->id,
                'total' => $subTotal + $iva,
            ];

            $input['base' . $product->percentage] = $subTotal;
            if ($product->percentage !== 0) {
                $input['iva' . $product->percentage] = $iva;
            }

            $orders[] = $input;
            $orderItems[] = [
                'product_id' => $product->id,
                'quantity' => $productData[3],
                'price' => $productData[4],
                'iva' => $product->iva,
            ];
            $emisionPoint->invoice++;
        }

        $orders = $branch->orders()->createMany($orders);
        $emisionPoint->save();

        $i = 0;
        foreach ($orders as $item) {
            $item->orderitems()->create($orderItems[$i++]);
        }

        // Firmar
        $orderXmlController = new OrderXmlController();
        foreach ($orders as $item) {
            $orderXmlController->xml($item->id, false);
        }

        // Crea Lote
        $orderXmlController->createLot($lot->id);

        //Envia Lote
        (new WSSriOrderController())->sendLote($lot->id);
    }

    private function getData(Request $request)
    {
        // Obtener el archivo Excel subido
        $file = $request->file('lot');

        // Cargar el archivo Excel
        $spreadsheet = IOFactory::load($file->getPathname());

        // Obtener la primera hoja del archivo
        $sheet = $spreadsheet->getActiveSheet();

        $excelData = [];

        $start = false;
        // Iterar sobre las filas del archivo Excel
        foreach ($sheet->getRowIterator() as $row) {
            if ($start) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false); // Recorrer todas las celdas, incluso si están vacías

                // Leer los datos de cada celda en la fila
                $rowData = [];
                foreach ($cellIterator as $cell) {
                    $rowData[] = $cell->getValue(); // Agregar el valor de la celda al array de datos
                }

                // Agregar los datos de la fila al array de datos de Excel
                $excelData[] = $rowData;
            }
            $start = true;
        }
        return $excelData;
    }
}
