<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define roles
        $roles = [
            'Super Admin',
            'Admin',
            'Subadmin',
            'HR Manager',
            'BIM Manager',
            'BIM Assistant Manager',
            'BIM Team Lead',
            'BIM Coordinator',
            'BIM Modeler',
            'BIM Technician',
            'BIM Engineer',
            'Employee',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'api']);
        }
    }
}
