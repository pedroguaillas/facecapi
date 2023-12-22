<?php

namespace Database\Seeders;

use App\Models\MethodOfPayment;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MethodOfPaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        MethodOfPayment::insert([
            ['code' => 1, 'description' => 'SIN UTILIZACION DEL SISTEMA FINANCIERO'],
            ['code' => 15, 'description' => 'COMPENSACIÓN DE DEUDAS'],
            ['code' => 16, 'description' => 'TARJETA DE DÉBITO'],
            ['code' => 17, 'description' => 'DINERO ELECTRÓNICO'],
            ['code' => 18, 'description' => 'TARJETA PREPAGO'],
            ['code' => 19, 'description' => 'TARJETA DE CRÉDITO'],
            ['code' => 20, 'description' => 'OTROS CON UTILIZACION DEL SISTEMA FINANCIERO'],
            ['code' => 21, 'description' => 'ENDOSO DE TÍTULOS']
        ]);
    }
}
