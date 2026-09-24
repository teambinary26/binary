<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
});

test('admin can open roles and update staff page access', function () {
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $staffRole = Role::query()->where('slug', 'staff')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('admin.roles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Roles/Index')
            ->has('roles')
            ->has('catalog')
        );

    $this->actingAs($admin)
        ->from(route('admin.roles.index'))
        ->put(route('admin.roles.update', $staffRole), [
            'permission_slugs' => [
                'dashboard.view',
                'applications.view',
                'applications.verify',
            ],
        ])
        ->assertRedirect(route('admin.roles.index'))
        ->assertSessionHas('success');

    $staffRole->refresh()->load('permissions');

    expect($staffRole->permissions->pluck('slug')->all())
        ->toContain('dashboard.view')
        ->toContain('applications.view')
        ->toContain('applications.verify')
        ->not->toContain('programs.view')
        ->not->toContain('roles.manage');
});

test('staff lose access to a page after the admin removes that permission', function () {
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $staff = User::query()->where('email', 'staff@nabua.gov.ph')->firstOrFail();
    $staffRole = Role::query()->where('slug', 'staff')->firstOrFail();

    $this->actingAs($staff)->get(route('admin.programs.index'))->assertOk();

    $this->actingAs($admin)
        ->put(route('admin.roles.update', $staffRole), [
            'permission_slugs' => [
                'dashboard.view',
                'applications.view',
            ],
        ])
        ->assertRedirect();

    $staff->unsetRelation('role');
    $staff->refresh();

    $this->actingAs($staff)->get(route('admin.programs.index'))->assertForbidden();
});

test('admin can choose which pages the sangguniang kabataan role can open', function () {
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $skUser = User::query()->where('email', 'sk@nabua.gov.ph')->firstOrFail();
    $skRole = Role::query()->where('slug', 'sk')->firstOrFail();

    $this->actingAs($skUser)->get(route('admin.programs.index'))->assertForbidden();

    $this->actingAs($admin)
        ->from(route('admin.roles.index'))
        ->put(route('admin.roles.update', $skRole), [
            'permission_slugs' => [
                'dashboard.view',
                'applications.view',
                'applications.verify',
                'programs.view',
            ],
        ])
        ->assertRedirect(route('admin.roles.index'))
        ->assertSessionHas('success', 'Sangguniang Kabataan page access has been updated.');

    $skRole->refresh()->load('permissions');

    expect($skRole->permissions->pluck('slug')->all())
        ->toContain('dashboard.view')
        ->toContain('programs.view')
        ->not->toContain('roles.manage');

    $skUser->unsetRelation('role');
    $skUser->refresh();

    $this->actingAs($skUser)->get(route('admin.programs.index'))->assertOk();
    $this->actingAs($skUser)->get(route('admin.users.index'))->assertForbidden();
});

test('administrator and applicant roles cannot be edited from this page', function () {
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $administrator = Role::query()->where('slug', 'administrator')->firstOrFail();
    $applicant = Role::query()->where('slug', 'applicant')->firstOrFail();

    foreach ([$administrator, $applicant] as $role) {
        $this->actingAs($admin)
            ->from(route('admin.roles.index'))
            ->put(route('admin.roles.update', $role), [
                'permission_slugs' => ['dashboard.view'],
            ])
            ->assertRedirect(route('admin.roles.index'))
            ->assertSessionHasErrors('role');
    }
});
