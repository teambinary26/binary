<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(): Response
    {
        $roles = Role::query()->with('permissions')->withCount('users')->orderBy('name')->get();
        $catalog = Permission::query()
            ->orderBy('module')
            ->orderBy('name')
            ->get()
            ->groupBy('module')
            ->map(fn ($permissions, $module) => [
                'module' => $module,
                'permissions' => $permissions->map(fn (Permission $permission) => [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'slug' => $permission->slug,
                    'locked' => in_array($permission->slug, $this->lockedStaffSlugs(), true),
                ])->values(),
            ])
            ->values();

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'users_count' => $role->users_count,
                'can_edit' => $role->slug === 'staff',
                'permissions' => $role->permissions->map(fn ($permission) => [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'slug' => $permission->slug,
                ])->values(),
            ])->values(),
            'catalog' => $catalog,
        ]);
    }

    public function update(Request $request, Role $role, AuditService $audit): RedirectResponse
    {
        if ($role->slug !== 'staff') {
            throw ValidationException::withMessages([
                'role' => 'Only staff page access can be changed from this page.',
            ]);
        }

        $data = $request->validate([
            'permission_slugs' => ['nullable', 'array'],
            'permission_slugs.*' => ['string', 'exists:permissions,slug'],
        ]);

        $slugs = collect($data['permission_slugs'] ?? [])
            ->reject(fn (string $slug) => in_array($slug, $this->lockedStaffSlugs(), true))
            ->push('dashboard.view');

        foreach ($this->impliedViews() as $action => $view) {
            if ($slugs->contains($action)) {
                $slugs->push($view);
            }
        }

        $ids = Permission::query()
            ->whereIn('slug', $slugs->unique()->values())
            ->pluck('id');

        $role->permissions()->sync($ids);
        $audit->log('updated', 'Updated staff page access', subject: $role);

        return back()->with('success', 'Staff page access has been updated.');
    }

    /**
     * @return list<string>
     */
    private function lockedStaffSlugs(): array
    {
        return ['roles.manage'];
    }

    /**
     * @return array<string, string>
     */
    private function impliedViews(): array
    {
        return [
            'applicants.manage' => 'applicants.view',
            'applications.manage' => 'applications.view',
            'applications.verify' => 'applications.view',
            'applications.evaluate' => 'applications.view',
            'applications.approve' => 'applications.view',
            'programs.manage' => 'programs.view',
            'releases.manage' => 'releases.view',
            'releases.verify' => 'releases.view',
            'announcements.manage' => 'announcements.view',
            'users.manage' => 'users.view',
            'settings.manage' => 'settings.view',
        ];
    }
}
