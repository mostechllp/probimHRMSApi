<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendanceLog extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'company_id', 'userid', 'log_date', 'punch_in', 'punch_out', 'status', 'device_id', 'log_status',
        'attendance_status', 'punch_in_latitude', 'punch_in_longitude', 'punch_in_address',
        'punch_out_latitude', 'punch_out_longitude', 'punch_out_address', 'timezone','working_hours'
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'userid', 'id');
    }
}
