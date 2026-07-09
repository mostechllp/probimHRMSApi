<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeSalaryPackage extends Model
{
    protected $fillable = [
        'name',
        'currency',
        'is_active'
    ];

    public function salaryComponents()
    {
        return $this->hasMany(EmployeeSalaryComponent::class, 'employee_salary_package_id');
    }

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
