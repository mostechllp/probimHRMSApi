<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'module_id',
        'title',
        'description',
        'screenshot',
        'priority',
        'status',
        'notes'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function getScreenshotUrlAttribute()
    {
        return $this->screenshot
            ? asset('storage/' . $this->screenshot)
            : null;
    }
}
