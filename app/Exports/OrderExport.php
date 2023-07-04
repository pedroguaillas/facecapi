<?php

namespace App\Exports;

use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\FromCollection;

class OrderExport implements FromCollection, WithHeadings, WithMapping
{
    private $month;
    public function __construct($month)
    {
        $this->month = $month;
    }
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);
        $branch = $company->branches->first();

        $year = substr($this->month, 0, 4);
        $month = substr($this->month, 5, 2);

        return DB::table('orders AS o')
            ->join('customers AS c', 'c.id', 'customer_id')
            ->select('o.*', 'c.*')
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->where('o.branch_id', $branch->id)
            ->get();
    }

    public function headings(): array
    {
        return [
            'Tipo de identificación', 'Identificación', 'Cliente',

            'Tipo de comprobante', 'Fecha de emisión', 'Autorización',
            'N° de comprobante', 'No objeto de iva', 'Base imponible no grabada',
            'Base imponible grabada', 'IVA', 'Total'
        ];
    }

    public function map($order): array
    {
        return [
            $order->type_identification, $order->identication, $order->name,

            $this->vtconvertion($order->voucher_type),
            $order->date, $order->authorization, $order->serie, $order->no_iva,
            $order->base0, $order->base12, $order->iva, $order->total

        ];
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
            case 5:
                return 'Nota de crédito';
        }
    }
}
