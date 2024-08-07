<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $casts = [
        'base8' => 'float',
        'iva8' => 'float',
    ];

    protected $fillable = [
        'branch_id', 'date',
        'description', 'sub_total',
        'serie', 'customer_id',
        'doc_realeted', 'expiration_days',
        'no_iva', 'base0', 'base12',
        'ice', 'iva', 'discount',
        'total', 'voucher_type',
        'paid',
        // Electronic
        'state', 'autorized',
        'authorization', 'iva_retention',
        'rent_retention', 'xml',
        'extra_detail', 'send_mail',
        // Guia de Remisión
        'guia',
        // Retencion
        'serie_retencion', 'date_retention',
        'authorization_retention',
        // Metodo de pago
        'pay_method',
        // Nota de Credito
        'date_order', 'serie_order', 'reason',
        // Cambio del % IVA
        'base5', 'base8', 'base15', 'iva5', 'iva8', 'iva15',
        // Envio por lotes
        'lot_id',
    ];

    public function orderitems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function orderaditionals()
    {
        return $this->hasMany(OrderAditional::class);
    }

    public function inventories()
    {
        return $this->hasMany(Inventory::class, 'model_id', 'id');
    }
}
