<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IvaTaxSeed extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('iva_taxes')->insert([
            [
                'code' => 0,
                'percentage' => 0,
                'state' => 'active',
            ],
            [
                'code' => 5,
                'percentage' => 5,
                'state' => 'active',
            ],
            [
                'code' => 2,
                'percentage' => 12,
                'state' => 'active',
            ],
            [
                'code' => 10,
                'percentage' => 13,
                'state' => 'active',
            ],
            [
                'code' => 4,
                'percentage' => 15,
                'state' => 'active',
            ],
        ]);
    }
}
