<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $users = [
            [
                'first_name' => 'Moris',
                'last_name' => 'Hilton',
                'email' => 'moris@email.com',
                'password' => Hash::make('password'),
                'role' => 'ADMIN'
            ]
        ];

        DB::table('users')->insert($users);
    }
}
