<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Employee;
use Illuminate\Support\Facades\Hash;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $role = \App\Models\Role::where('name', 'Employee')->first();

        $user = User::create([
            'username' => 'anu@gmail.com',
            'email' => 'anu@gmail.com',
            'password' => Hash::make('anu@probim'),
            'type' => 'employee',
            'status' => 'active',
            'role_id' => $role?->id,
        ]);

        Employee::create([
            'user_id' => $user->id,
            'first_name' => 'Anu',
            'last_name' => 'Mohan',
            'employee_id' => 'EMP',
            'company_email' => 'anu@gmail.com',
            'personal_email' => 'anu@gmail.com',
            'personal_number' => '9876543210',
            'joining_date' => now(),
        ]);
    }
}