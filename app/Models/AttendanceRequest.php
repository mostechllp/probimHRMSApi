<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceRequest extends Model
{
    protected $fillable = [
        'employee_id',
        'type',
        'request_date',
        'request_time',
        'reason',
        'status',
        'timezone',
        'created_by',
        'project_times',
        'punch_in_time',
        'punch_out_time',
        'location',
        'work_location'
    ];

    protected $casts = [
        'project_times' => 'array',
        'location' => 'array',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
