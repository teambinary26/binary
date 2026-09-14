<?php

use App\Enums\ApplicationStatus;
use App\Enums\BeneficiaryType;
use App\Mail\ApplicationAcceptedMail;
use App\Mail\ApplicationApprovedMail;
use App\Mail\ApplicationOtpMail;
use App\Mail\ApplicationReceivedMail;
use App\Models\Application;
use App\Models\AssistanceProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * Controls the faked Cloudflare siteverify result for this file.
 * Pass a value to set it; call with no argument to read it.
 */
function turnstileFakeSucceeds(?bool $succeeds = null): bool
{
    static $value = true;

    if ($succeeds !== null) {
        $value = $succeeds;
    }

    return $value;
}

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    $this->seed();
    Mail::fake();
    turnstileFakeSucceeds(true);
    Http::fake(function () {
        $ok = turnstileFakeSucceeds();

        return Http::response([
            'success' => $ok,
            'error-codes' => $ok ? [] : ['invalid-input-response'],
        ], 200);
    });
    config([
        'services.turnstile.secret_key' => 'test-secret',
        'services.turnstile.verify_url' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
    ]);
});

function publicApplyFields(string $otp, array $overrides = []): array
{
    return array_merge([
        'full_name' => 'Ana Public Applicant',
        'date_of_birth' => '2005-03-15',
        'sex' => 'female',
        'street' => '12 Mabini Street',
        'barangay' => 'Poblacion',
        'municipality' => 'Nabua',
        'province' => 'Camarines Sur',
        'contact_number' => '09171234567',
        'email' => 'ana.public@example.com',
        'mother_name' => 'Rosa Applicant',
        'mother_occupation' => 'Vendor',
        'father_name' => 'Pedro Applicant',
        'father_occupation' => 'Farmer',
        'is_pwd' => 0,
        'school_name' => 'Nabua National High School',
        'course_or_program' => 'General Academic Strand',
        'year_level' => 'Grade 12',
        'beneficiary_type' => 'student',
        'otp' => $otp,
        'eligibility_confirmed' => 1,
        'turnstile_token' => 'test-token',
    ], $overrides);
}

function openStudentProgram(): AssistanceProgram
{
    return AssistanceProgram::query()
        ->where('is_open', true)
        ->whereIn('beneficiary_type', [BeneficiaryType::Student->value, BeneficiaryType::Both->value])
        ->firstOrFail();
}

function capturedOtp(?string $email = null): string
{
    $otp = null;

    Mail::assertSent(ApplicationOtpMail::class, function (ApplicationOtpMail $mail) use (&$otp, $email) {
        $otp = $mail->otp;

        return $email === null || $mail->hasTo($email);
    });

    expect($otp)->toBeString()->not->toBeEmpty();

    return (string) $otp;
}

test('apply page includes eligibility details for an open program', function () {
    $program = openStudentProgram();

    $this->get(route('site.apply.create', $program))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/Programs/Apply')
            ->where('program.slug', $program->slug)
            ->where('turnstilePassed', false)
            ->has('program.eligibility')
            ->has('program.eligibility_rules')
        );
});

test('cloudflare siteverify is required before the apply steps continue', function () {
    $program = openStudentProgram();

    $this->postJson(route('site.apply.turnstile', $program), [
        'turnstile_token' => 'good-token',
    ])->assertOk()->assertJson(['ok' => true]);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'turnstile/v0/siteverify')
        && $request['secret'] === 'test-secret'
        && $request['response'] === 'good-token');

    $this->get(route('site.apply.create', $program))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('turnstilePassed', true));
});

test('failed cloudflare siteverify keeps the apply steps locked', function () {
    $program = openStudentProgram();
    turnstileFakeSucceeds(false);

    $this->postJson(route('site.apply.turnstile', $program), [
        'turnstile_token' => 'bad-token',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('turnstile_token');

    $this->get(route('site.apply.create', $program))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('turnstilePassed', false));
});

test('applicant apply start opens the public apply page', function () {
    $user = User::query()->where('email', 'juan.delacruz@email.com')->firstOrFail();
    $program = openStudentProgram();

    $this->actingAs($user)
        ->get(route('applicant.apply.start', $program))
        ->assertRedirect(route('site.apply.create', $program));
});

test('public apply requires eligibility confirmation', function () {
    $program = openStudentProgram();

    $this->from((string) route('site.apply.create', $program))
        ->post((string) route('site.apply.store', $program), publicApplyFields('123456', [
            'eligibility_confirmed' => 0,
        ]))
        ->assertRedirect(route('site.apply.create', $program))
        ->assertSessionHasErrors('eligibility_confirmed');
});

test('guest can apply with otp and the record is stored as a draft', function () {
    $program = openStudentProgram();

    $this->postJson(route('site.apply.otp', $program), [
        'turnstile_token' => 'test-token',
        'full_name' => 'Ana Public Applicant',
        'email' => 'ana.public@example.com',
    ])->assertOk();

    $otp = capturedOtp('ana.public@example.com');

    $response = $this->from((string) route('site.programs.index'))
        ->post((string) route('site.apply.store', $program), publicApplyFields($otp));

    $application = Application::query()
        ->whereHas('applicant', fn ($query) => $query->where('email', 'ana.public@example.com'))
        ->firstOrFail();

    $response
        ->assertRedirect(route('site.apply.success'))
        ->assertSessionHas('apply_success');

    $this->assertGuest();

    expect($application->status)->toBe(ApplicationStatus::Draft)
        ->and($application->applicant->user->pending_account)->toBeTrue()
        ->and($application->applicant->user->is_active)->toBeFalse()
        ->and($application->applicant->profile->mother_name)->toBe('Rosa Applicant')
        ->and($application->applicant->profile->course_or_program)->toBe('General Academic Strand')
        ->and($application->applicant->profile->year_level)->toBe('Grade 12')
        ->and($application->applicant->profile->is_pwd)->toBeFalse();

    Mail::assertSent(ApplicationReceivedMail::class, function (ApplicationReceivedMail $mail) {
        return $mail->hasTo('ana.public@example.com') && blank($mail->password);
    });
});

test('pending applicant cannot log in until an admin approves', function () {
    $program = openStudentProgram();

    $this->postJson(route('site.apply.otp', $program), [
        'turnstile_token' => 'test-token',
        'full_name' => 'Ana Public Applicant',
        'email' => 'ana.pending@example.com',
    ])->assertOk();

    $otp = capturedOtp('ana.pending@example.com');

    $this->post((string) route('site.apply.store', $program), publicApplyFields($otp, [
        'email' => 'ana.pending@example.com',
    ]))->assertRedirect();

    $this->from((string) route('login'))
        ->post((string) route('login.store'), [
            'email' => 'ana.pending@example.com',
            'password' => 'anything-since-the-real-one-was-never-shared',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('admin can approve a pending applicant which emails their credentials', function () {
    $program = openStudentProgram();

    $this->postJson(route('site.apply.otp', $program), [
        'turnstile_token' => 'test-token',
        'full_name' => 'Ana Public Applicant',
        'email' => 'ana.approve@example.com',
    ])->assertOk();

    $otp = capturedOtp('ana.approve@example.com');

    $this->post((string) route('site.apply.store', $program), publicApplyFields($otp, [
        'email' => 'ana.approve@example.com',
    ]))->assertRedirect();

    $application = Application::query()
        ->whereHas('applicant', fn ($query) => $query->where('email', 'ana.approve@example.com'))
        ->firstOrFail();

    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();

    $this->actingAs($admin)
        ->from((string) route('admin.applications.show', $application))
        ->post((string) route('admin.applications.approve-applicant', $application), ['remarks' => 'Looks good.'])
        ->assertRedirect(route('admin.applications.show', $application))
        ->assertSessionHas('success');

    $account = $application->applicant->user->fresh();

    expect($account->is_active)->toBeTrue()
        ->and($account->pending_account)->toBeFalse()
        ->and($application->fresh()->status)->toBe(ApplicationStatus::Accepted);

    Mail::assertSent(ApplicationAcceptedMail::class, function (ApplicationAcceptedMail $mail) {
        return $mail->hasTo('ana.approve@example.com')
            && $mail->loginEmail === 'ana.approve@example.com'
            && filled($mail->password);
    });
});

test('admin can reject a pending applicant before portal access is granted', function () {
    $program = openStudentProgram();

    $this->postJson(route('site.apply.otp', $program), [
        'turnstile_token' => 'test-token',
        'full_name' => 'Ana Public Applicant',
        'email' => 'ana.reject@example.com',
    ])->assertOk();

    $otp = capturedOtp('ana.reject@example.com');

    $this->post((string) route('site.apply.store', $program), publicApplyFields($otp, [
        'email' => 'ana.reject@example.com',
    ]))->assertRedirect();

    $application = Application::query()
        ->whereHas('applicant', fn ($query) => $query->where('email', 'ana.reject@example.com'))
        ->firstOrFail();

    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();

    $this->actingAs($admin)
        ->from((string) route('admin.applications.show', $application))
        ->post((string) route('admin.applications.reject-applicant', $application), ['remarks' => 'Incomplete personal information.'])
        ->assertRedirect(route('admin.applications.show', $application))
        ->assertSessionHas('success');

    $application->refresh();
    $account = $application->applicant->user->fresh();

    expect($application->status)->toBe(ApplicationStatus::Rejected)
        ->and($account->is_active)->toBeFalse()
        ->and($account->pending_account)->toBeTrue();
});

test('public apply stores pwd type when the applicant is a pwd', function () {
    $program = openStudentProgram();

    $this->postJson(route('site.apply.otp', $program), [
        'turnstile_token' => 'test-token',
        'full_name' => 'Ana Public Applicant',
        'email' => 'ana.pwd@example.com',
    ])->assertOk();

    $otp = capturedOtp('ana.pwd@example.com');

    $this->post((string) route('site.apply.store', $program), publicApplyFields($otp, [
        'email' => 'ana.pwd@example.com',
        'is_pwd' => 1,
        'pwd_type' => 'visual',
    ]))->assertRedirect();

    $application = Application::query()
        ->whereHas('applicant', fn ($query) => $query->where('email', 'ana.pwd@example.com'))
        ->firstOrFail();

    expect($application->applicant->profile->is_pwd)->toBeTrue()
        ->and($application->applicant->profile->pwd_type)->toBe('visual');
});

test('public apply rejects an invalid otp', function () {
    $program = openStudentProgram();

    $this->from((string) route('site.programs.index'))
        ->post((string) route('site.apply.store', $program), publicApplyFields('000000'))
        ->assertRedirect(route('site.programs.index'))
        ->assertSessionHasErrors('otp');
});

test('apply success page renders when apply_success flash is set', function () {
    $this->withSession(['apply_success' => [
        'application_no' => 'CAMS-2026-000123',
        'program_name' => 'Educational Assistance',
        'email' => 'ana.public@example.com',
    ]])
        ->get(route('site.apply.success'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/Programs/ApplySuccess')
            ->where('result.application_no', 'CAMS-2026-000123')
            ->where('result.email', 'ana.public@example.com')
        );
});

test('apply success page redirects to programs when accessed directly', function () {
    $this->get(route('site.apply.success'))
        ->assertRedirect(route('site.programs.index'));
});

test('otp is expired from cache after 10 minutes', function () {
    $program = openStudentProgram();

    $this->postJson(route('site.apply.otp', $program), [
        'turnstile_token' => 'test-token',
        'full_name' => 'Ana Public Applicant',
        'email' => 'ana.public@example.com',
    ])->assertOk();

    $otp = capturedOtp();

    // Fast-forward 10 minutes + 1 second and confirm the OTP is no longer valid.
    $this->travel(10)->minutes();
    $this->travel(1)->seconds();

    $this->from((string) route('site.apply.create', $program))
        ->post((string) route('site.apply.store', $program), publicApplyFields($otp))
        ->assertSessionHasErrors('otp');
});

test('admin approval emails a generated password for pending applicants', function () {
    $program = openStudentProgram();

    $this->postJson(route('site.apply.otp', $program), [
        'turnstile_token' => 'test-token',
        'full_name' => 'Ana Public Applicant',
        'email' => 'ana.public@example.com',
    ])->assertOk();

    $otp = capturedOtp();

    $this->post((string) route('site.apply.store', $program), publicApplyFields($otp))->assertRedirect();

    $application = Application::query()
        ->whereHas('applicant', fn ($query) => $query->where('email', 'ana.public@example.com'))
        ->firstOrFail();

    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();

    $this->actingAs($admin)
        ->from((string) route('admin.applications.show', $application))
        ->post((string) route('admin.applications.decide', $application), [
            'decision' => 'approved',
            'approved_amount' => 5000,
            'remarks' => 'Approved for assistance.',
        ])
        ->assertRedirect();

    $application->refresh();
    $account = $application->applicant->user->fresh();

    expect($application->status)->toBe(ApplicationStatus::Approved)
        ->and($account->is_active)->toBeTrue()
        ->and($account->pending_account)->toBeFalse();

    Mail::assertSent(ApplicationApprovedMail::class, function (ApplicationApprovedMail $mail) {
        return $mail->hasTo('ana.public@example.com') && filled($mail->password);
    });
});
