<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Carbon\Carbon;

class Employee extends Model
{
    use SoftDeletes, Notifiable;

    /**
     * Scope a query to include inactive employees.
     */
    public function scopeWithInactive($query)
    {
        return $query->withoutGlobalScope('active');
    }

    /**
     * Scope a query to only inactive employees.
     */
    public function scopeOnlyInactive($query)
    {
        return $query->withoutGlobalScope('active')->where('status', 'inactive');
    }

    /**
     * Retrieve the model for a bound value.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->withInactive()->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'employee_id',
        'dob',
        'joining_date',
        'gender',
        'special_days',
        'passport_full_name',
        'passport_number',
        'passport_issued_from',
        'passport_issued_date',
        'passport_expiry_date',
        'place_of_birth',
        'father_name',
        'mother_name',
        'address',
        'passport_1st_page',
        'passport_2nd_page',
        'passport_outer_page',
        'passport_id_page',
        'visa_number',
        'visa_issued_date',
        'visa_expiry_date',
        'visa_page',
        'labor_number',
        'labor_issued_date',
        'labor_expiry_date',
        'labor_card',
        'eid_number',
        'eid_issued_date',
        'eid_expiry_date',
        'eid_1st_page',
        'eid_2nd_page',
        'dependents',
        'educational_1st_page',
        'educational_2nd_page',
        'company_mobile_number',
        'personal_number',
        'other_number',
        'home_country_number',
        'company_email',
        'personal_email',
        'home_country_id_proof',
        'status',
        'avatar',
        'marital_status',
        'nationality',
        'visa_type',
        'is_skilled',
        'additional_documents',
        'experience_level',
        'key_skills',
        'highest_education',
        'currency',
        'payment_cycle'
    ];

    protected $casts = [
        'special_days' => 'array',
        'is_skilled' => 'boolean',
        'additional_documents' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getAvatarUrlAttribute()
    {
        if ($this->avatar) {
            return asset('storage/' . $this->avatar);
        }
        return $this->user ? $this->avatar : 'https://ui-avatars.com/api/?name=' . urlencode($this->first_name) . '&color=fff&background=2ecc71';
    }

    public function attendanceLogs()
    {
        return $this->hasMany(AttendanceLog::class, 'userid', 'employee_id');
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function leaveAllocations()
    {
        return $this->hasMany(LeaveAllocation::class);
    }

    public function salaryPackages()
    {
        return $this->hasMany(EmployeeSalaryPackage::class);
    }

    public function salaryComponents()
    {
        return $this->hasMany(EmployeeSalaryComponent::class);
    }

    public function bankDetails()
    {
        return $this->hasMany(EmployeeBankDetail::class);
    }

    public function projects()
    {
        return $this->belongsToMany(
            Project::class,
            'employee_project',
            'employee_id',   // foreign key on pivot
            'project_id', // related key on pivot
            'user_id',   // local key on Employee model
            'id'         // local key on Project model
        )
            ->withPivot('assigned_by', 'deleted_by', 'deleted_at')
            ->withTimestamps()
            ->wherePivotNull('deleted_at');
    }
}
