<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectTimeLog extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'project_id',
        'date',
        'time_taken_minutes',
        'status',
        'description',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'time_taken_minutes' => 'integer',
        'approved_at' => 'datetime',
    ];

    protected $appends = [
        'hours',
        'formatted_hours',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get hours as decimal float (e.g. 8.5).
     */
    public function getHoursAttribute(): float
    {
        $minutes = (float) ($this->time_taken_minutes ?? 0);
        return round($minutes / 60, 2);
    }

    /**
     * Get formatted hours string matching the UI (e.g. 8.5h or 9h).
     */
    public function getFormattedHoursAttribute(): string
    {
        $hours = $this->getHoursAttribute();
        if (floor($hours) == $hours) {
            return ((int) $hours) . 'h';
        }
        return $hours . 'h';
    }
}
