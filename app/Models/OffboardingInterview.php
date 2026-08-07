<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OffboardingInterview extends Model
{
    use HasFactory;

    protected $fillable = [
        'offboarding_id',
        'interviewer',
        'interview_date',
        'interview_mode',
        'overall_satisfaction',
        'primary_reason',
        'work_life_rating',
        'manager_relationship_rating',
        'enjoyed_most',
        'areas_for_improvement',
        'would_recommend'
    ];

    public function offboarding()
    {
        return $this->belongsTo(Offboarding::class);
    }
}

