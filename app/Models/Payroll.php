<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payroll extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'pay_period_month',
        'pay_period_year',
        'gross_salary',
        'overtime',
        'deductions',
        'net_pay',
        'currency',
        'status',
        'current_step',
        'data'
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'user_id', 'user_id');
    }
}
