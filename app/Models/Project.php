<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'project_manager_id',
        'team_lead_id',
        'created_by',
        'deleted_by',
    ];

    public function projectManager()
    {
        return $this->belongsTo(Employee::class, 'project_manager_id');
    }

    public function teamLead()
    {
        return $this->belongsTo(Employee::class, 'team_lead_id');
    }

    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'employee_project')
            ->using(ProjectAssignment::class)
            ->withPivot('assigned_by', 'deleted_by', 'deleted_at')
            ->wherePivot('deleted_at', null);
    }
}
