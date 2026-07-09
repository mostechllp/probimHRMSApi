<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeSalaryComponent extends Model
{
    protected $fillable = [
        'employee_id',
        'employee_salary_package_id',
        'component_name',
        'value'
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function package()
    {
        return $this->belongsTo(EmployeeSalaryPackage::class, 'employee_salary_package_id');
    }
}
