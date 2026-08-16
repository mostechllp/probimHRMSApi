<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Offboarding extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'reporting_manager_id',
        'status',
        'last_working_day',
        'resignation_date',
        'separation_type',
        'notice_period_days',
        'notice_start_date',
        'visa_sponsorship',
        'nationality',
        'reason_for_leaving',
        'cancellation_status',
        'cancellation_date',
        'cancellation_reference',
        'cancellation_document',
        'cancellation_remarks',
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

    public function reportingManager()
    {
        return $this->belongsTo(Employee::class, 'reporting_manager_id', 'id');
    }

}

