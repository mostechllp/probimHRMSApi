<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EmployeeAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'offboarding_id',
        'asset_name',
        'asset_code',
        'issued_on',
        'status',
        'condition'
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function offboarding()
    {
        return $this->belongsTo(Offboarding::class);
    }
}
