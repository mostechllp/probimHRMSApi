<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Offboarding extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'status',
        'last_working_day',
        'separation_type',
        'notice_period_days',
        'notice_start_date',
        'visa_sponsorship',
        'nationality',
        'reason_for_leaving'
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function checklists()
    {
        return $this->hasMany(OffboardingChecklist::class);
    }

    public function assets()
    {
        return $this->hasMany(EmployeeAsset::class);
    }

    public function interview()
    {
        return $this->hasOne(OffboardingInterview::class);
    }

    public function settlement()
    {
        return $this->hasOne(OffboardingSettlement::class);
    }

    public function letters()
    {
        return $this->hasMany(OffboardingLetter::class);
    }
}

