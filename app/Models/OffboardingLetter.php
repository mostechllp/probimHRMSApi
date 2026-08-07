<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OffboardingLetter extends Model
{
    use HasFactory;

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

