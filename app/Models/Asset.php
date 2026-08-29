<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    protected $fillable = [
        'asset_type_id',
        'asset_name',
        'brand',
        'model',
        'serial_number',
        'purchase_price',
        'purchase_date',
        'warranty_expiry',
        'status'
    ];

    public function type()
    {
        return $this->belongsTo(AssetType::class, 'asset_type_id');
    }

    public function assigned_to()
    {
        return $this->hasMany(AssetAssignment::class);
    }
}
