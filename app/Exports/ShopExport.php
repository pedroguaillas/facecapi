<?php

namespace App\Exports;

use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\FromCollection;

class ShopExport implements FromCollection, WithHeadings, WithMapping
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

        $query = '(SELECT value FROM shop_retention_items WHERE shop_id = s.id AND tax_code = 1) AS r30,';
        $query .= '(SELECT value FROM shop_retention_items WHERE shop_id = s.id AND tax_code = 10) AS r20,';
        $query .= '(SELECT value FROM shop_retention_items WHERE shop_id = s.id AND tax_code = 11) AS r50,';
        $query .= '(SELECT value FROM shop_retention_items WHERE shop_id = s.id AND tax_code = 2) AS r70,';
        $query .= '(SELECT value FROM shop_retention_items WHERE shop_id = s.id AND tax_code = 3) AS r100,';
        $query .= '(SELECT value FROM shop_retention_items WHERE shop_id = s.id AND tax_code = 9) AS r10';

        return DB::table('shops AS s')
            ->join('providers AS p', 'p.id', 'provider_id')
            ->select('s.*', 'p.*', DB::raw($query))
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->where('s.branch_id', $branch->id)
            ->get();
    }

    public function headings(): array
    {
        return [
            'Tipo de identificación', 'Identificación', 'Proveedor',

            'Tipo de comprobante', 'Fecha de emisión', 'Autorización',
            'N° de comprobante', 'No objeto de iva', 'Base imponible no grabada',
            'Base imponible grabada', 'IVA', 'Total',

            'Ret IVA 10%', 'Ret IVA 20%', 'Ret IVA 30%',
            'Ret IVA 50%', 'Ret IVA 70%', 'Ret IVA 100%'
        ];
    }

    public function map($shop): array
    {
        return [
            $shop->type_identification, $shop->identication, $shop->name,

            $this->vtconvertion($shop->voucher_type), $shop->date,
            $shop->authorization, $shop->serie, $shop->no_iva,
            $shop->base0, $shop->base12, $shop->iva, $shop->total,


            $shop->r10, $shop->r20, $shop->r30, $shop->r50, $shop->r70, $shop->r100
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
