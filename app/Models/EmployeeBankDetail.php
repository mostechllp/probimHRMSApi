<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeBankDetail extends Model
{
    protected $fillable = [
        'employee_id',
        'bank_country',
        'bank_name',
        'account_number',
        'iban_number',
        'swift_code',
        'branch_name',
        'ifsc_code'
    ];
}
