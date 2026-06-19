<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkingHour extends Model
{
    protected $fillable = [
        'day',
        'is_enabled',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];
}
