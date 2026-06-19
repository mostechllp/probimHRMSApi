<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        DB::table('companies')->insert([
            [
                'organization_id' => 1, // change if needed
                'company_name' => 'THESAY Pharma',
                'phone' => null,
                'email' => null,
                'logo' => null,
                'address' => 'India',
                'created_by' => 1,
                'deleted_by' => null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'organization_id' => 1,
                'company_name' => 'Saygen Genetics',
                'phone' => null,
                'email' => null,
                'logo' => null,
                'address' => 'India',
                'created_by' => 1,
                'deleted_by' => null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'organization_id' => 1,
                'company_name' => 'Farmassay',
                'phone' => null,
                'email' => null,
                'logo' => null,
                'address' => 'India',
                'created_by' => 1,
                'deleted_by' => null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'organization_id' => 1,
                'company_name' => 'THESAY Warehouse',
                'phone' => null,
                'email' => null,
                'logo' => null,
                'address' => 'India',
                'created_by' => 1,
                'deleted_by' => null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'organization_id' => 1,
                'company_name' => 'SAA Pharma',
                'phone' => null,
                'email' => null,
                'logo' => null,
                'address' => 'India',
                'created_by' => 1,
                'deleted_by' => null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'organization_id' => 1,
                'company_name' => 'THESAY Veterinary',
                'phone' => null,
                'email' => null,
                'logo' => null,
                'address' => 'India',
                'created_by' => 1,
                'deleted_by' => null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);
    }
}