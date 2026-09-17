<?php

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Auth\GoogleLoginController;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\AssistanceProgram;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();

    config([
        'services.google.client_id' => 'test-google-client-id',
        'services.google.client_secret' => 'test-google-client-secret',
        'services.google.redirect' => 'http://localhost/login/google/callback',
    ]);
});

function fakeGoogleLogin(string $email, bool $verified = true): void
{
    $googleUser = (new GoogleUser)->map([
        'id' => 'google-'.md5($email),
        'nickname' => null,
        'name' => 'Google User',
        'email' => $email,
        'avatar' => null,
    ]);

    $googleUser->user = [
        'email' => $email,
        'email_verified' => $verified,
        'verified_email' => $verified,
    ];

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->once()->andReturn($googleUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

function createPendingApplicantWithApplication(string $email): User
{
    $user = User::query()->create([
        'role_id' => Role::query()->where('slug', 'applicant')->value('id'),
        'name' => 'Pending Google Applicant',
        'email' => $email,
        'password' => 'placeholder-password',
        'is_active' => false,
        'pending_account' => true,
    ]);

    $applicant = Applicant::query()->create([
        'user_id' => $user->id,
        'applicant_no' => 'BEN-GOOGLE-'.substr(md5($email), 0, 8),
        'full_name' => 'Pending Google Applicant',
        'date_of_birth' => '2005-03-15',
        'sex' => 'female',
        'contact_number' => '09171234567',
        'email' => $email,
    ]);

    Application::query()->create([
        'application_no' => 'APP-GOOGLE-'.substr(md5($email), 0, 8),
        'applicant_id' => $applicant->id,
        'assistance_program_id' => AssistanceProgram::query()->value('id'),
        'status' => ApplicationStatus::Draft,
        'current_step' => 1,
    ]);

    return $user;
}

test('google sign-in redirects guests to google when configured', function () {
    $response = $this->get(route('login.google'));

    $response->assertRedirect();
    expect((string) $response->headers->get('Location'))->toContain('accounts.google.com');
});

test('google sign-in is blocked when credentials are missing', function () {
    config([
        'services.google.client_id' => '',
        'services.google.client_secret' => '',
    ]);

    $this->from(route('login'))
        ->get(route('login.google'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('google');

    $this->assertGuest();
});

test('approved applicant can sign in with a matching google email', function () {
    fakeGoogleLogin('juan.delacruz@email.com');

    $this->get(route('login.google.callback'))
        ->assertRedirect('/applicant/dashboard');

    $this->assertAuthenticated();
    expect(Auth::user()?->email)->toBe('juan.delacruz@email.com');
});

test('staff can sign in with a matching google email', function () {
    fakeGoogleLogin('admin@nabua.gov.ph');

    $this->get(route('login.google.callback'))
        ->assertRedirect('/admin/dashboard');

    $this->assertAuthenticated();
});

test('unapproved applicant with the same application email cannot google sign in', function () {
    $email = 'pending.google@example.com';
    createPendingApplicantWithApplication($email);
    fakeGoogleLogin($email);

    $this->from(route('login'))
        ->get(route('login.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors([
            'google' => GoogleLoginController::PENDING_APPLICATION_MESSAGE,
        ]);

    $this->assertGuest();
});

test('google sign-in does not create an account when the email is unknown', function () {
    $email = 'unknown.google.login@example.com';
    $usersBefore = User::query()->count();

    fakeGoogleLogin($email);

    $this->from(route('login'))
        ->get(route('login.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors([
            'google' => GoogleLoginController::UNKNOWN_ACCOUNT_MESSAGE,
        ]);

    $this->assertGuest();
    expect(User::query()->count())->toBe($usersBefore)
        ->and(User::query()->where('email', $email)->exists())->toBeFalse()
        ->and(Applicant::query()->where('email', $email)->exists())->toBeFalse();
});

test('unverified google emails cannot sign in', function () {
    fakeGoogleLogin('juan.delacruz@email.com', verified: false);

    $this->from(route('login'))
        ->get(route('login.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('google');

    $this->assertGuest();
});
