<?php

namespace App\Models;

use App\Enums\WorkflowStep;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'role_id',
        'name',
        'employee_no',
        'office',
        'email',
        'password',
        'is_active',
        'pending_account',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $with = [
        'role',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'pending_account' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function applicant(): HasOne
    {
        return $this->hasOne(Applicant::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(SystemNotification::class);
    }

    public function unreadNotifications(): HasMany
    {
        return $this->notifications()->whereNull('read_at');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('administrator');
    }

    public function isSuperAdmin(): bool
    {
        return $this->isAdmin();
    }

    public function isApplicant(): bool
    {
        return $this->hasRole('applicant');
    }

    public function isSk(): bool
    {
        return $this->hasRole('sk');
    }

    public function isStaff(): bool
    {
        return $this->isAdmin() || $this->hasRole('staff', 'sk');
    }

    public function hasRole(string ...$slugs): bool
    {
        if (! $this->role) {
            return false;
        }

        return in_array($this->role->slug, $slugs, true);
    }

    public function hasPermission(string $permission): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->isAdmin()) {
            return true;
        }

        $step = WorkflowStep::tryFromPermission($permission);
        if ($step) {
            return $this->roleHasPermission('applications.view')
                && WorkflowStaff::isAssignedToStep($this->id, $step);
        }

        return $this->roleHasPermission($permission);
    }

    /**
     * @return list<string>
     */
    public function permissionSlugs(): array
    {
        if (! $this->is_active) {
            return [];
        }

        if ($this->isAdmin()) {
            return Permission::query()->pluck('slug')->values()->all();
        }

        $this->loadMissing('role.permissions');
        $slugs = collect($this->role?->permissions->pluck('slug')->all() ?? [])
            ->reject(fn (string $slug) => WorkflowStep::tryFromPermission($slug) !== null)
            ->values();

        foreach (WorkflowStep::ordered() as $step) {
            if ($this->hasPermission($step->permission())) {
                $slugs->push($step->permission());
            }
        }

        return $slugs->unique()->values()->all();
    }

    private function roleHasPermission(string $permission): bool
    {
        $this->loadMissing('role.permissions');

        return $this->role?->permissions->contains('slug', $permission) ?? false;
    }

    public function canAccessAdmin(): bool
    {
        return $this->is_active && $this->isStaff();
    }

    public function homePath(): string
    {
        if ($this->canAccessAdmin()) {
            return '/admin/dashboard';
        }

        if ($this->isApplicant()) {
            return '/applicant/dashboard';
        }

        return '/';
    }
}
