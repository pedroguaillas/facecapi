<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{

    protected $fillable = [
        'ruc',
        'company',
        'economic_activity',
        'accounting',
        'micro_business',
        'retention_agent',
        'phone',
        'logo_dir',
        'cert_dir',
        'pass_cert',
        'sign_valid_from',
        'sign_valid_to',
        'enviroment_type',
        'decimal',

        // Ajuste base5 solo para ferreterias
        'base5',

        // Ajuste base8 solo para turistas
        'base8',
        'tourism_from',
        'tourism_to',

        // Ajuste ICE
        'ice',

        //Agregado las 2 columas el 25 de enero del 2022
        'rimpe',
        'inventory',

        // Metodo de pago
        'pay_method',

        // New facec.ec
        'printf',               // Imprimir en impresora termica
        'guia_in_invoice',      // Mostrar guia de remision al crear factura
        'import_in_invoice',    // Importar excel con la lista de productos para crear factura
        'import_in_invoices',    // Importar excel en ventas factura
    ];

    protected $casts = [
        'base8' => 'boolean',
        'tourism_from' => 'date',
        'tourism_to' => 'date',
        'inventory' => 'boolean',
        'ice' => 'boolean',

        // New facec.ec
        'printf' => 'boolean',
        'guia_in_invoice' => 'boolean',
        'import_in_invoice' => 'boolean',
        'import_in_invoices' => 'boolean',
    ];

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }
}
