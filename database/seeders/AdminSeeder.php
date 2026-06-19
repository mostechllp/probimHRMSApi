<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Employee;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $role = \App\Models\Role::where('name', 'Admin')->first();

        $user = User::create([
            'username' => 'hr@probim.ae',
            'email' => 'hr@probim.ae',
            'password' => Hash::make('HR@probim2026'),
            'type' => 'admin',
            'status' => 'active', 
            'role_id' => $role?->id,
        ]);

        $employee = Employee::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'user_id' => $user->id,
            'employee_id' => '1000',
            'company_email' => 'hr@probim.ae',
            'personal_email' => 'ajmal@gmail.com',
        ]);

    }
}