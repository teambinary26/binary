<?php

use App\Enums\ApplicationStatus;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\AssistanceProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    $this->seed();
});

function incomingDraftApplication(): Application
{
    return Application::query()->create([
        'application_no' => 'CAMS-INCOMING-0001',
        'applicant_id' => Applicant::query()->value('id'),
        'assistance_program_id' => AssistanceProgram::query()->value('id'),
        'status' => ApplicationStatus::Draft,
        'current_step' => 1,
    ]);
}

test('unaccepted applications stay on the incoming tab instead of the main table', function () {
    $draft = incomingDraftApplication();
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('admin.applications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Applications/Index')
            ->where('tab', 'all')
            ->where('canAccept', true)
            ->where('counts.incoming', fn ($count) => $count >= 1)
            ->where('applications.data', fn ($rows) => collect($rows)->every(
                fn ($row) => $row['id'] !== $draft->id && $row['status'] !== 'draft'
            ))
            ->where('statuses', fn ($statuses) => collect($statuses)->every(
                fn ($status) => $status['value'] !== 'draft'
            ))
        );

    $this->actingAs($admin)
        ->get(route('admin.applications.index', ['tab' => 'incoming']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab', 'incoming')
            ->where('applications.data', fn ($rows) => collect($rows)->contains(
                fn ($row) => $row['id'] === $draft->id
            ))
        );
});

test('status draft opens the incoming applications tab', function () {
    incomingDraftApplication();
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('admin.applications.index', ['status' => 'draft']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('tab', 'incoming'));
});

test('staff can accept incoming applications and sk cannot unless granted', function () {
    incomingDraftApplication();
    $staff = User::query()->where('email', 'staff@nabua.gov.ph')->firstOrFail();
    $sk = User::query()->where('email', 'sk@nabua.gov.ph')->firstOrFail();

    $this->actingAs($staff)
        ->get(route('admin.applications.index', ['tab' => 'incoming']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canAccept', true));

    $this->actingAs($sk)
        ->get(route('admin.applications.index', ['tab' => 'incoming']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canAccept', false));
});
