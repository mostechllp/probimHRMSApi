<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Module;
use App\Models\Role;
use App\Models\RolePermission;

class RBACSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Create Modules
        |--------------------------------------------------------------------------
        */

        $modules = [
            ['name' => 'Dashboard',           'slug' => 'dashboard',           'route' => '/dashboard',           'icon' => 'bx-grid-alt',        'status' => 'active'],
            ['name' => 'Onboarding',          'slug' => 'onboarding',          'route' => '/onboarding',          'icon' => 'bx-user-plus',       'status' => 'active'],
            ['name' => 'Employees',           'slug' => 'employees',           'route' => '/employees',           'icon' => 'bx-group',           'status' => 'active'],
            ['name' => 'Projects',            'slug' => 'projects',            'route' => '/projects',            'icon' => 'bx-briefcase',       'status' => 'active'],
            ['name' => 'Project Assignments', 'slug' => 'project-assignments', 'route' => '/project-assignments', 'icon' => 'bx-user-check',      'status' => 'active'],
            ['name' => 'Attendance',          'slug' => 'attendance',          'route' => '/attendance',          'icon' => 'bx-fingerprint',     'status' => 'active'],
            ['name' => 'Leaves',              'slug' => 'leaves',              'route' => '/leaves',              'icon' => 'bx-calendar-check',  'status' => 'active'],
            ['name' => 'Task Reports',        'slug' => 'task-reports',        'route' => '/task-reports',        'icon' => 'bx-list-ul',         'status' => 'active'],
            ['name' => 'WFH Requests',        'slug' => 'wfh-requests',        'route' => '/wfh-requests',        'icon' => 'bx-home',            'status' => 'active'],
            ['name' => 'Reports',             'slug' => 'reports',             'route' => '/reports',             'icon' => 'bx-bar-chart-alt-2', 'status' => 'active'],
            ['name' => 'Payroll',             'slug' => 'payroll',             'route' => '/payroll',             'icon' => 'bx-dollar-circle',   'status' => 'active'],
            ['name' => 'Roles',               'slug' => 'roles',               'route' => '/roles',               'icon' => 'bx-shield',          'status' => 'active'],
            ['name' => 'Organizations',       'slug' => 'organizations',       'route' => '/organizations',       'icon' => 'bx-building',        'status' => 'active'],
            ['name' => 'Agreements',          'slug' => 'agreements',          'route' => '/agreements',          'icon' => 'bx-file',            'status' => 'active'],
            ['name' => 'Settings',            'slug' => 'settings',            'route' => '/settings',            'icon' => 'bx-cog',             'status' => 'active'],
        ];

        foreach ($modules as $module) {
            Module::updateOrCreate(
                ['slug' => $module['slug']],
                $module
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Create Roles
        |--------------------------------------------------------------------------
        */

        $roles = [

            [
                'name' => 'Super Admin',
                'guard_name' => 'api',
                'description' => 'Full access to all modules and system settings',
            ],

            [
                'name' => 'Admin',
                'guard_name' => 'api',
                'description' => 'Administrative access to system modules',
            ],

            [
                'name' => 'Subadmin',
                'guard_name' => 'api',
                'description' => 'Limited administrative access',
            ],

            [
                'name' => 'HR Manager',
                'guard_name' => 'api',
                'description' => 'Leave, payroll, documents & notifications',
            ],

            [
                'name' => 'BIM Manager',
                'guard_name' => 'api',
                'description' => 'Self-assign + full assignment rights; senior management',
            ],

            [
                'name' => 'BIM Assistant Manager',
                'guard_name' => 'api',
                'description' => 'Can assign to Coordinators & Modelers; no self-assign',
            ],

            [
                'name' => 'BIM Team Lead',
                'guard_name' => 'api',
                'description' => 'Can assign to Coordinators & Modelers; leave suggestion only',
            ],

            [
                'name' => 'BIM Coordinator',
                'guard_name' => 'api',
                'description' => 'Receives assigned tasks only',
            ],

            [
                'name' => 'BIM Modeler',
                'guard_name' => 'api',
                'description' => 'Handles BIM modeling tasks',
            ],

            [
                'name' => 'BIM Technician',
                'guard_name' => 'api',
                'description' => 'Technical BIM support role',
            ],

            [
                'name' => 'BIM Engineer',
                'guard_name' => 'api',
                'description' => 'Engineering and BIM coordination role',
            ],

            [
                'name' => 'Employee',
                'guard_name' => 'api',
                'description' => 'Regular employee access',
            ],
        ];

        foreach ($roles as $roleData) {

            $role = Role::updateOrCreate(
                [
                    'name' => $roleData['name'],
                    'guard_name' => 'api'
                ],
                $roleData
            );

            /*
            |--------------------------------------------------------------------------
            | Give Super Admin Full Access
            |--------------------------------------------------------------------------
            */

            if (in_array($role->name, ['Super Admin', 'Admin'])) {

                $allModules = Module::all();

                foreach ($allModules as $module) {

                    RolePermission::updateOrCreate(
                        [
                            'role_id' => $role->id,
                            'module_id' => $module->id,
                        ],
                        [
                            'can_read' => true,
                            'can_edit' => true,
                            'can_delete' => true,
                        ]
                    );
                }
            }
        }
    }
}