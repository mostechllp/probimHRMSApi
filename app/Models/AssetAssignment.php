<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetAssignment extends Model
{
    protected $fillable = [
        'asset_id',
        'employee_id',
        'assigned_date',
        'returned_date',
        'return_condition',
        'notes',
        'status'
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class)
            ->select('id', 'first_name', 'last_name');
    }
}
