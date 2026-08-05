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
        'created_by'
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
