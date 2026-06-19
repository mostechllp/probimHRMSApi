<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Designation extends Model
{
    protected $fillable = ['name', 'default_punch_access'];

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
