<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    public const MUNICIPAL_SLUGS = ['administrator', 'sk', 'staff'];

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
}
