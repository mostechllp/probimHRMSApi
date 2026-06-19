<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['name' => 'Sick Leave', 'status' => true],
            ['name' => 'Casual Leave', 'status' => true],
            ['name' => 'Annual Leave', 'status' => true],
            ['name' => 'Unpaid Leave', 'status' => true],
            ['name' => 'Maternity Leave', 'status' => true],
            ['name' => 'Paternity Leave', 'status' => true],
        ];

        foreach ($types as $type) {
            \App\Models\LeaveType::updateOrCreate(['name' => $type['name'], 'created_by' => NULL, 'deleted_by' => NULL], $type);
        }
    }
}
