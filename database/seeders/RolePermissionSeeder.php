<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['Dashboard', 'dashboard.view', 'Dashboard'],
            ['View applicants', 'applicants.view', 'Beneficiaries'],
            ['Manage applicants', 'applicants.manage', 'Beneficiaries'],
            ['View applications', 'applications.view', 'Applications'],
            ['Manage applications', 'applications.manage', 'Applications'],
            ['Verify documents', 'applications.verify', 'Applications'],
            ['Evaluate applications', 'applications.evaluate', 'Applications'],
            ['Approve applications', 'applications.approve', 'Applications'],
            ['View programs', 'programs.view', 'Programs'],
            ['Manage programs', 'programs.manage', 'Programs'],
            ['View releases', 'releases.view', 'Releases'],
            ['Manage releases', 'releases.manage', 'Releases'],
            ['Verify releases', 'releases.verify', 'Releases'],
            ['View reports', 'reports.view', 'Reports'],
            ['View announcements', 'announcements.view', 'Communication'],
            ['Manage announcements', 'announcements.manage', 'Communication'],
            ['View users', 'users.view', 'System'],
            ['Manage users', 'users.manage', 'System'],
            ['Manage roles', 'roles.manage', 'System'],
            ['View audit logs', 'audit.view', 'System'],
            ['View settings', 'settings.view', 'System'],
            ['Manage settings', 'settings.manage', 'System'],
        ];

        $permissionIds = [];
        foreach ($permissions as [$name, $slug, $module]) {
            $permissionIds[$slug] = Permission::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'module' => $module]
            )->id;
        }

        $roles = [
            'administrator' => [
                'name' => 'Administrator',
                'description' => 'Full municipal access: users, programs, applications, releases, reports, and settings.',
                'permissions' => array_keys($permissionIds),
            ],
            'staff' => [
                'name' => 'Staff',
                'description' => 'Process applications, verify documents, evaluate eligibility, and record releases.',
                'permissions' => [
                    'dashboard.view',
                    'applicants.view', 'applicants.manage',
                    'applications.view', 'applications.manage',
                    'applications.verify', 'applications.evaluate', 'applications.approve',
                    'programs.view',
                    'releases.view', 'releases.manage', 'releases.verify',
                    'reports.view',
                    'announcements.view',
                ],
            ],
            'sk' => [
                'name' => 'Sangguniang Kabataan',
                'description' => 'SK officials assigned to document verification. They can review applications and verify submitted requirements.',
                'permissions' => [
                    'dashboard.view',
                    'applicants.view',
                    'applications.view',
                    'applications.verify',
                ],
            ],
            'applicant' => [
                'name' => 'Applicant',
                'description' => 'Registered citizen account for online applications.',
                'permissions' => [],
            ],
        ];

        foreach ($roles as $slug => $role) {
            $model = Role::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $role['name'], 'description' => $role['description']]
            );

            $ids = array_map(fn ($perm) => $permissionIds[$perm], $role['permissions']);
            $model->permissions()->sync($ids);
        }

        $kept = Role::query()->whereIn('slug', array_keys($roles))->pluck('id', 'slug');
        $legacyMap = [
            'super_administrator' => 'administrator',
            'verifier' => 'staff',
            'evaluator' => 'staff',
            'approving_officer' => 'staff',
            'release_officer' => 'staff',
            'encoder' => 'staff',
        ];

        foreach ($legacyMap as $oldSlug => $newSlug) {
            $oldRole = Role::query()->where('slug', $oldSlug)->first();
            if (! $oldRole || ! isset($kept[$newSlug])) {
                continue;
            }

            User::query()->where('role_id', $oldRole->id)->update(['role_id' => $kept[$newSlug]]);
            $oldRole->permissions()->detach();
            $oldRole->delete();
        }

        Role::query()
            ->whereNotIn('slug', array_keys($roles))
            ->get()
            ->each(function (Role $role) use ($kept) {
                $fallback = $role->isApplicant() ? ($kept['applicant'] ?? null) : ($kept['staff'] ?? null);
                if ($fallback) {
                    User::query()->where('role_id', $role->id)->update(['role_id' => $fallback]);
                }
                $role->permissions()->detach();
                $role->delete();
            });

        $this->renameEmail('admin@sanrafael.gov.ph', 'admin@nabua.gov.ph');
        $this->renameEmail('superadmin@sanrafael.gov.ph', 'admin@nabua.gov.ph');
        $this->renameEmail('verifier@sanrafael.gov.ph', 'staff@nabua.gov.ph');
        $this->renameEmail('evaluator@sanrafael.gov.ph', 'staff@nabua.gov.ph');
        $this->renameEmail('approver@sanrafael.gov.ph', 'staff@nabua.gov.ph');
        $this->renameEmail('release@sanrafael.gov.ph', 'staff@nabua.gov.ph');
        $this->renameEmail('encoder@sanrafael.gov.ph', 'staff@nabua.gov.ph');
    }

    private function renameEmail(string $from, string $to): void
    {
        $source = User::query()->where('email', $from)->first();
        if (! $source) {
            return;
        }

        if (User::query()->where('email', $to)->exists()) {
            $source->update(['is_active' => false]);

            return;
        }

        $source->update(['email' => $to]);
    }
}
