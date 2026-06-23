<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

use Illuminate\Database\Eloquent\SoftDeletes;


class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected $guard_name = 'api';

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'email',
        'password',
        'avatar',
        'organization_id',
        'company_id',
        'department_id',
        'designation_id',
        'role_id',
        'type',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getAvatarUrlAttribute()
    {
        if ($this->avatar) {
            return asset('storage/' . $this->avatar);
        }
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->username) . '&color=fff&background=2ecc71';
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function hasPermission($moduleSlug, $permissionType)
    {
        if (!$this->role) {
            return false;
        }

        // Admin has all permissions
        if ($this->role->name === 'Admin') {
            return true;
        }

        $permission = $this->role->permissions()
            ->whereHas('module', function ($query) use ($moduleSlug) {
                $query->where('slug', $moduleSlug);
            })
            ->first();

        if (!$permission) {
            return false;
        }

        $column = 'can_' . $permissionType;
        return (bool) $permission->$column;
    }

    public function employee()
    {
        return $this->hasOne(Employee::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function designation()
    {
        return $this->belongsTo(Designation::class, 'designation_id');
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class, 'employee_project', 'employee_id', 'project_id')
            ->withPivot('assigned_by', 'deleted_by', 'deleted_at')
            ->withTimestamps()
            ->wherePivot('deleted_at', null);
    }
}
