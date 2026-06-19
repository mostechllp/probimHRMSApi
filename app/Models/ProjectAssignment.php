<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectAssignment extends Pivot
{
    use SoftDeletes;

    protected $table = 'employee_project';

    public $incrementing = true;

    // ← ADD THIS
    protected $fillable = [
        'employee_id',
        'project_id',
        'assigned_by',
        'deleted_by',
        'deleted_at',
    ];

    protected $casts = [
        'deleted_at' => 'datetime',  // ← ADD THIS
    ];
}