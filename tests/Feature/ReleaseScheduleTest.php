<?php

use App\Enums\ApplicationStatus;
use App\Mail\ReleaseScheduledMail;
use App\Models\Application;
use App\Models\AssistanceRelease;
use App\Models\ReleaseSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
});

test('release schedule page lists approved applicants separately from scheduled releases', function () {
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('admin.releases.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Releases/Index')
            ->has('approved.data')
            ->has('forRelease')
            ->has('programs')
            ->has('filters')
            ->has('schedules.data')
            ->where('approved.data', fn ($rows) => collect($rows)->every(fn ($row) => $row['status'] === ApplicationStatus::Approved->value))
            ->where('forRelease', fn ($rows) => collect($rows)->every(fn ($row) => $row['status'] === ApplicationStatus::ScheduledForRelease->value))
        );
});

test('actual release cannot be recorded before the application is scheduled', function () {
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $application = Application::query()
        ->where('status', ApplicationStatus::Approved)
        ->firstOrFail();

    $this->actingAs($admin)
        ->from(route('admin.releases.index'))
        ->post(route('admin.releases.record'), [
            'application_id' => $application->id,
            'amount' => $application->approved_amount ?: 1000,
            'remarks' => 'Walk-in release',
        ])
        ->assertRedirect(route('admin.releases.index'))
        ->assertSessionHasErrors('application');

    expect(AssistanceRelease::query()->where('application_id', $application->id)->exists())->toBeFalse()
        ->and($application->fresh()->status)->toBe(ApplicationStatus::Approved);
});

test('release schedule page can filter approved applicants by program and search', function () {
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $application = Application::query()
        ->where('status', ApplicationStatus::Approved)
        ->with(['applicant', 'program'])
        ->firstOrFail();

    $this->actingAs($admin)
        ->get(route('admin.releases.index', [
            'program' => $application->assistance_program_id,
            'q' => $application->applicant->full_name,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.program', $application->assistance_program_id)
            ->where('filters.q', $application->applicant->full_name)
            ->where('approved.data', fn ($rows) => collect($rows)->contains('id', $application->id))
        );

    $this->actingAs($admin)
        ->get(route('admin.releases.index', ['q' => 'NO-SUCH-APPLICANT-XYZ']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('approved.data', 0)
        );
});

test('multiple approved applicants can be scheduled together', function () {
    Mail::fake();

    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $applications = Application::query()
        ->whereIn('status', [
            ApplicationStatus::Approved,
            ApplicationStatus::Submitted,
            ApplicationStatus::UnderVerification,
        ])
        ->take(2)
        ->get();

    expect($applications)->toHaveCount(2);

    foreach ($applications as $application) {
        $application->update([
            'status' => ApplicationStatus::Approved,
            'approved_amount' => $application->approved_amount ?: 1000,
        ]);
    }

    $ids = $applications->pluck('id')->all();

    $this->actingAs($admin)
        ->from(route('admin.releases.index'))
        ->post(route('admin.releases.schedule'), [
            'application_ids' => $ids,
            'release_date' => now()->addDays(2)->toDateString(),
            'release_location' => 'MSWDO Window 2, Nabua Local Government Center',
            'release_method' => 'cash',
            'notes' => 'Batch release.',
        ])
        ->assertRedirect(route('admin.releases.index'))
        ->assertSessionHas('success');

    foreach ($applications as $application) {
        expect($application->fresh()->status)->toBe(ApplicationStatus::ScheduledForRelease);
    }

    Mail::assertSent(ReleaseScheduledMail::class, 2);
});

test('actual release can be recorded after the application is scheduled', function () {
    Mail::fake();

    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $application = Application::query()
        ->where('status', ApplicationStatus::Approved)
        ->with('applicant.user')
        ->firstOrFail();

    $this->actingAs($admin)
        ->from(route('admin.releases.index'))
        ->post(route('admin.releases.schedule'), [
            'application_id' => $application->id,
            'release_date' => now()->addDays(3)->toDateString(),
            'release_location' => 'MSWDO Window 2, Nabua Local Government Center',
            'release_method' => 'cash',
            'notes' => 'Bring one valid ID.',
        ])
        ->assertRedirect(route('admin.releases.index'))
        ->assertSessionHas('success');

    expect($application->fresh()->status)->toBe(ApplicationStatus::ScheduledForRelease);

    Mail::assertSent(ReleaseScheduledMail::class, function (ReleaseScheduledMail $mail) use ($application) {
        $email = $application->applicant->email ?: $application->applicant->user?->email;

        return $mail->hasTo($email)
            && $mail->application->is($application)
            && $mail->schedule->application_id === $application->id;
    });

    $this->actingAs($admin)
        ->from(route('admin.releases.index'))
        ->post(route('admin.releases.record'), [
            'application_id' => $application->id,
            'amount' => $application->approved_amount ?: 1000,
            'remarks' => 'Released over the counter.',
        ])
        ->assertRedirect(route('admin.releases.index'))
        ->assertSessionHas('success');

    expect($application->fresh()->status)->toBe(ApplicationStatus::Released)
        ->and(AssistanceRelease::query()->where('application_id', $application->id)->exists())->toBeTrue();
});

test('release schedule page can filter schedules by date and status', function () {
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $schedule = ReleaseSchedule::query()
        ->where('status', 'scheduled')
        ->firstOrFail();
    $date = $schedule->release_date->toDateString();

    $this->actingAs($admin)
        ->get(route('admin.releases.index', [
            'release_date' => $date,
            'schedule_status' => 'scheduled',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.release_date', $date)
            ->where('filters.schedule_status', 'scheduled')
            ->where('schedules.data', fn ($rows) => collect($rows)->isNotEmpty()
                && collect($rows)->every(fn ($row) => $row['release_date_input'] === $date && $row['status'] === 'scheduled'))
        );
});

test('a scheduled release can be rescheduled and emails the applicant', function () {
    Mail::fake();

    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $schedule = ReleaseSchedule::query()
        ->where('status', 'scheduled')
        ->whereHas('application', fn ($query) => $query->where('status', ApplicationStatus::ScheduledForRelease))
        ->with('application.applicant.user')
        ->firstOrFail();
    $newDate = now()->addDays(12)->toDateString();

    $this->actingAs($admin)
        ->from(route('admin.releases.index'))
        ->put(route('admin.releases.reschedule', $schedule), [
            'release_date' => $newDate,
            'release_location' => 'Municipal Hall, San Rafael',
            'release_method' => 'cash',
            'notes' => 'Moved due to a holiday.',
        ])
        ->assertRedirect(route('admin.releases.index'))
        ->assertSessionHas('success');

    $schedule->refresh();

    expect($schedule->release_date->toDateString())->toBe($newDate)
        ->and($schedule->release_location)->toBe('Municipal Hall, San Rafael')
        ->and($schedule->application->fresh()->status)->toBe(ApplicationStatus::ScheduledForRelease);

    Mail::assertSent(ReleaseScheduledMail::class, function (ReleaseScheduledMail $mail) use ($schedule) {
        $email = $schedule->application->applicant->email ?: $schedule->application->applicant->user?->email;

        return $mail->hasTo($email)
            && $mail->rescheduled
            && $mail->schedule->is($schedule);
    });
});

test('a completed release cannot be rescheduled', function () {
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $schedule = ReleaseSchedule::query()->where('status', 'completed')->firstOrFail();

    $this->actingAs($admin)
        ->from(route('admin.releases.index'))
        ->put(route('admin.releases.reschedule', $schedule), [
            'release_date' => now()->addWeek()->toDateString(),
            'release_location' => $schedule->release_location,
            'release_method' => $schedule->release_method,
            'notes' => 'Should not change.',
        ])
        ->assertRedirect(route('admin.releases.index'))
        ->assertSessionHasErrors('schedule');

    expect($schedule->fresh()->status)->toBe('completed');
});

test('multiple scheduled releases can be rescheduled together', function () {
    Mail::fake();

    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $applications = Application::query()
        ->whereIn('status', [
            ApplicationStatus::Approved,
            ApplicationStatus::ScheduledForRelease,
            ApplicationStatus::Submitted,
        ])
        ->take(2)
        ->get();

    expect($applications)->toHaveCount(2);

    $schedules = $applications->map(function (Application $application) use ($admin) {
        $application->update([
            'status' => ApplicationStatus::ScheduledForRelease,
            'approved_amount' => $application->approved_amount ?: 1000,
        ]);

        $existing = $application->releaseSchedules()->where('status', 'scheduled')->latest('id')->first();

        if ($existing) {
            return $existing;
        }

        return $application->releaseSchedules()->create([
            'release_date' => now()->addDays(5)->toDateString(),
            'release_location' => 'MSWDO Window 2, Nabua Local Government Center',
            'release_method' => 'cash',
            'status' => 'scheduled',
            'scheduled_by' => $admin->id,
            'notes' => 'Prepared for batch reschedule.',
        ]);
    });

    expect($schedules)->toHaveCount(2);

    $newDate = now()->addDays(8)->toDateString();

    $this->actingAs($admin)
        ->from(route('admin.releases.index'))
        ->post(route('admin.releases.reschedule-many'), [
            'schedule_ids' => $schedules->pluck('id')->all(),
            'release_date' => $newDate,
            'release_location' => 'MSWDO Window 2, Nabua Local Government Center',
            'release_method' => 'cash',
            'notes' => 'Batch reschedule.',
        ])
        ->assertRedirect(route('admin.releases.index'))
        ->assertSessionHas('success');

    foreach ($schedules as $schedule) {
        expect($schedule->fresh()->release_date->toDateString())->toBe($newDate);
    }

    Mail::assertSent(ReleaseScheduledMail::class, 2);
});

test('released assistance page can be filtered by program and search', function () {
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $release = AssistanceRelease::query()
        ->with(['application.applicant', 'application.program'])
        ->firstOrFail();

    $this->actingAs($admin)
        ->get(route('admin.releases.released', [
            'program' => $release->application->assistance_program_id,
            'q' => $release->application->applicant->full_name,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Releases/Released')
            ->has('programs')
            ->has('barangays')
            ->where('filters.program', $release->application->assistance_program_id)
            ->where('filters.q', $release->application->applicant->full_name)
            ->where('releases.data', fn ($rows) => collect($rows)->contains('id', $release->id))
        );

    $this->actingAs($admin)
        ->get(route('admin.releases.released', ['q' => 'NO-SUCH-RELEASE-XYZ']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('releases.data', [])
        );
});

test('released assistance can be exported to excel and pdf', function () {
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $release = AssistanceRelease::query()->firstOrFail();

    $excel = $this->actingAs($admin)->get(route('admin.releases.released', ['export' => 'excel']));
    $excel->assertOk();
    expect($excel->getContent())
        ->toContain($release->reference_no)
        ->toContain('Applicant');

    $pdf = $this->actingAs($admin)->get(route('admin.releases.released', ['export' => 'pdf']));
    $pdf->assertOk();
    expect($pdf->headers->get('content-type'))->toStartWith('application/pdf')
        ->and($pdf->getContent())->toStartWith('%PDF');
});

test('claim verification page renders the lookup form', function () {
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('admin.releases.verify'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Releases/Verify')
            ->where('result', null)
            ->where('filters.method', 'application_no')
            ->where('filters.lookup', '')
        );
});

test('claim verification lookup that does not match shows an invalid result', function () {
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();

    $this->actingAs($admin)
        ->post(route('admin.releases.verify.store'), [
            'method' => 'application_no',
            'lookup' => 'CAMS-0000-999999',
        ])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Releases/Verify')
            ->where('result.valid', false)
            ->where('result.result', 'invalid')
            ->where('result.release', null)
            ->where('filters.lookup', 'CAMS-0000-999999')
            ->where('filters.method', 'application_no')
        );
});

test('claim verification can find a released record by application number', function () {
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $release = AssistanceRelease::query()->with('application')->firstOrFail();

    $this->actingAs($admin)
        ->post(route('admin.releases.verify.store'), [
            'method' => 'application_no',
            'lookup' => $release->application->application_no,
        ])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Releases/Verify')
            ->where('result.valid', true)
            ->where('result.release.reference_no', $release->reference_no)
            ->where('filters.lookup', $release->application->application_no)
        );
});
