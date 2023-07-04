<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use Carbon\Carbon;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function store(Request $request)
    {
        $inventory = Inventory::create([
            'product_id' => $request->product_id,
            'quantity' => $request->quantity,
            'price' => $request->price,
            'type' => $request->type,
            'code_provider' => $request->code_provider,
            'date' => substr(Carbon::today()->toISOString(), 0, 10)
        ]);

        if ($inventory) {
            $product = $inventory->product;
            $product->stock === null ? 0 : $product->stock;
            if ($request->typ === 'Inventario inicial' || $request->type === 'Compra' || $request->type === 'Devolución en venta') {
                $product->stock += $request->quantity;
            } else {
                $product->stock -= $request->quantity;
            }
            $product->save();
        }
    }
}
