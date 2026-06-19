<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'name',
        'type',
        'description',
        'file_path',
        'folder_id',
        'party_id',
        'share_with',
        'expiry_date'
    ];

    protected $casts = [
        'share_with' => 'array',
    ];

    public function party()
    {
        return $this->belongsTo(Party::class, 'party_id', 'id');
    }

    public function folder()
    {
        return $this->belongsTo(Folder::class, 'folder_id', 'id');
    }

    public function getSharedUsersAttribute()
    {
        $ids = $this->share_with ?? [];
        if (empty($ids)) {
            return [];
        }

        return User::whereIn('id', $ids)
            ->with('employee')
            ->get(['id', 'username', 'type'])
            ->map(function ($user) {
                $name = $user->employee
                    ? $user->employee->first_name . ' ' . $user->employee->last_name
                    : $user->username;

                return [
                    'id' => $user->id,
                    'name' => trim($name),
                    'type' => $user->type
                ];
            });
    }
}
