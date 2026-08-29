<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class OffboardingSettlement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'offboarding_id',
        'total_payable',
        'total_deductions',
        'net_payable',
        'status',
        'remarks'
    ];

    public function offboarding()
    {
        return $this->belongsTo(Offboarding::class);
    }
}

