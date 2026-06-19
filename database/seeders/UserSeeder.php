<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminRole = \App\Models\Role::where('name', 'Admin')->first();

        if ($adminRole) {
            \App\Models\User::updateOrCreate(
                ['email' => 'admin@mostech.com'],
                [
                    'username' => 'admin',
                    'password' => \Illuminate\Support\Facades\Hash::make('password'),
                    'role_id' => $adminRole->id,
                    'status' => 'active',
                ]
            );
        }
    }
}
