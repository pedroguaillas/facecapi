<?php

namespace App\Services;

use App\Models\{Company, Branch, MethodOfPayment, Repayment, Order, OrderItem, OrderAditional};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class OrderPdfService
{
    public function buildPdf(int $id): array
    {
        $movement = Order::join('customers AS c', 'c.id', 'customer_id')
            ->select('orders.*', 'c.identication', 'c.name', 'c.address', 'c.email')
            ->where('orders.id', $id)
            ->firstOrFail();

        $after = Carbon::parse(
            $movement->voucher_type == 4 ? $movement->date_order : $movement->date
        )->isBefore('2024-04-01');

        $movement_items = OrderItem::join('products', 'products.id', 'product_id')
        ->select('products.*', 'order_items.*')
        ->where('order_id', $id)
        ->get();
        $enabledDiscount = $movement_items->contains(fn($item) => $item->discount > 0);
        $orderaditionals = OrderAditional::where('order_id', $id)->get();

        $auth = Auth::user();
        $company = Company::find($auth->companyusers->first()->level_id);
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
        
        if ($movement->voucher_type == 1) {
            $payMethod = MethodOfPayment::where('code', $movement->pay_method)->value('description');
            $repayments = Repayment::selectRaw('identification, sequential, date, SUM(base) base, SUM(iva) iva')
                ->join('repayment_taxes AS rt', 'repayments.id', 'repayment_id')
                ->where('order_id', $id)
                ->groupBy('identification', 'sequential', 'date')
                ->get();

            $pdf = app('dompdf.wrapper')->loadView(
                'vouchers.invoice',
                compact('company', 'branch', 'movement', 'movement_items', 'orderaditionals', 'after', 'enabledDiscount', 'payMethod', 'repayments')
            );
        } else {
            $pdf = app('dompdf.wrapper')->loadView(
                'vouchers.creditnote',
                compact('company', 'branch', 'movement', 'movement_items', 'orderaditionals', 'after', 'enabledDiscount')
            );
        }

        return [$pdf, $movement];
    }

    public function savePdf(int $id): void
    {
        [$pdf, $movement] = $this->buildPdf($id);
        $pdf->save(Storage::path(str_replace('.xml', '.pdf', $movement->xml)));
    }
}
