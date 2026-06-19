<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskReport extends Model
{
    protected $fillable = ['employee_id', 'date', 'tasks_completed', 'plan_tomorrow', 'remarks'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
