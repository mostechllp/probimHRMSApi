<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceUpload extends Model
{
    protected $fillable = [
        'company_id',
        'file_path',
        'total_records',
        'processed_records',
        'progress',
        'status'
    ];
}
