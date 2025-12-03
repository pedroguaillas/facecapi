<?php

namespace Database\Seeders; 

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;

class SriCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('sri_categories')->insert([
            ['code' => 'F010101', 'type' => 'ferreteria', 'description' => 'VARILLA LAMINADA CORRUGADA AS42 DE 8MM, 10MM Y 12MM DE DIÁMETRO'],
            ['code' => 'F010201', 'type' => 'ferreteria', 'description' => 'ARCILLA'],
            ['code' => 'F010202', 'type' => 'ferreteria', 'description' => 'ARENA'],
            ['code' => 'F010203', 'type' => 'ferreteria', 'description' => 'CAL'],
            ['code' => 'F010204', 'type' => 'ferreteria', 'description' => 'CALIZA'],
            ['code' => 'F010205', 'type' => 'ferreteria', 'description' => 'PÉTROS'],
            ['code' => 'F010301', 'type' => 'ferreteria', 'description' => 'HORMIGÓN PREMEZCLADO'],
            ['code' => 'F010401', 'type' => 'ferreteria', 'description' => 'CEMENTO Y SUS DERIVADOS'],
            ['code' => 'F010402', 'type' => 'ferreteria', 'description' => 'RESIDUO CEMENTO'],
            ['code' => 'F010501', 'type' => 'ferreteria', 'description' => 'CHATARRA FERROSA'],
            ['code' => 'F010601', 'type' => 'ferreteria', 'description' => 'MORTERO'],
            ['code' => 'F010701', 'type' => 'ferreteria', 'description' => 'CLINKER'],
            ['code' => 'F010702', 'type' => 'ferreteria', 'description' => 'PUZOLANA'],
            ['code' => 'F010703', 'type' => 'ferreteria', 'description' => 'YESO'],
            ['code' => 'F010801', 'type' => 'ferreteria', 'description' => 'ADOQUÍN'],
            ['code' => 'F010802', 'type' => 'ferreteria', 'description' => 'BLOQUES'],
            ['code' => 'F010803', 'type' => 'ferreteria', 'description' => 'LADRILLOS'],
            ['code' => 'F010804', 'type' => 'ferreteria', 'description' => 'PRODUCTOS DE HORMIGÓN PREFABRICADO'],
            ['code' => 'H492001', 'type' => 'transporte', 'description' => 'OPERADORA AL CLIENTE'],
            ['code' => 'H492002', 'type' => 'transporte', 'description' => 'SOCIO O ACCIONISTA O LA OPERADORA'],
        ]);
    }
}
