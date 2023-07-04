<?php

namespace Database\Seeders; 

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('users')->insert([
            'user' => 'admin',
            'email' => 'pier_surdito@hotmail.com',
            'password' => Hash::make('Laravel'),
            'user_type_id' => 1,
        ]);
    }
}
