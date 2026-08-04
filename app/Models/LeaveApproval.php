<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveApproval extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'leave_request_id',
        'approver_id',
        'approver_level',
        'status',
        'remark',
    ];

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function leaveRequest()
    {
        return $this->belongsTo(LeaveRequest::class);
    }
}
