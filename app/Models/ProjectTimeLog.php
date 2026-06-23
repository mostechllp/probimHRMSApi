<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectTimeLog extends Model
{
    protected $fillable = [
        'user_id',
        'project_id',
        'date',
        'time_taken_minutes'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
