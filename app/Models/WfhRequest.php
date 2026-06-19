<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WfhRequest extends Model
{
    protected $fillable = ['employee_id', 'date', 'reason', 'notes', 'status'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
