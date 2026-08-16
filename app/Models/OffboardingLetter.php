<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class OffboardingLetter extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'offboarding_id',
        'letter_type',
        'document_path',
        'status'
    ];

    public function offboarding()
    {
        return $this->belongsTo(Offboarding::class);
    }
}

