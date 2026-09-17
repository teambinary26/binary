<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    public const MUNICIPAL_SLUGS = ['administrator', 'sk', 'staff'];

    public const SK_PERMISSIONS = [
        'dashboard.view',
        'applicants.view',
        'applications.view',
        'applications.verify',
    ];

    protected $fillable = ['name', 'slug', 'description'];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isAdmin(): bool
    {
        return $this->slug === 'administrator';
    }

    public function isSuperAdmin(): bool
    {
        return $this->isAdmin();
    }

    public function isApplicant(): bool
    {
        return $this->slug === 'applicant';
    }

    public function isSk(): bool
    {
        return $this->slug === 'sk';
    }

    public static function ensureSk(): self
    {
        $role = static::query()->updateOrCreate(
            ['slug' => 'sk'],
            [
                'name' => 'Sangguniang Kabataan',
                'description' => 'SK officials assigned to document verification. They can review applications and verify submitted requirements.',
            ]
        );

        $permissionIds = Permission::query()
            ->whereIn('slug', self::SK_PERMISSIONS)
            ->pluck('id');

        if ($permissionIds->isNotEmpty() && $role->permissions()->count() === 0) {
            $role->permissions()->sync($permissionIds);
        }

        return $role;
    }
}
