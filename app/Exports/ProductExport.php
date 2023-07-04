<?php

namespace App\Exports;

use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\FromCollection;

class ProductExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct()
    {
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

        return DB::table('products AS p')
            ->select('code', 'type_product', 'name', 'price1', 'iva')
            ->where('branch_id', $branch->id)
            ->get();
    }

    public function headings(): array
    {
        return [
            'Codigo', 'Tipo', 'Nombre', 'Precio', 'Iva'
        ];
    }

    public function map($product): array
    {
        return [
            $product->code, $product->type_product, $product->name, $product->price1, $product->iva
        ];
    }
}
