<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class OffboardingChecklist extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'offboarding_id',
        'category_id',
        'task_name',
        'status',
        'responsible_role',
        'notes'
    ];

    public function offboarding()
    {
        return $this->belongsTo(Offboarding::class);
    }

    public function category()
    {
        return $this->belongsTo(
            OffboardingChecklistCategory::class,
            'category_id'
        );
    }
}

