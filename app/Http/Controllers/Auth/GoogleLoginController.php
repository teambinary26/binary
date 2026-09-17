<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;

class GoogleLoginController extends Controller
{
    public const PENDING_APPLICATION_MESSAGE = 'Your application still needs to be approved before you can sign in. Please wait for the office to approve it.';

    public const UNKNOWN_ACCOUNT_MESSAGE = 'No account was found for this Google email. Google sign-in is for existing approved accounts only and cannot be used to register.';

    public function redirect(): RedirectResponse
    {
        if (! $this->configured()) {
            return redirect()->route('login')->withErrors([
                'google' => 'Google sign-in is not configured yet. Please use your email and password, or try again later.',
            ]);
        }

        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(Request $request, AuditService $audit): RedirectResponse
    {
        if (! $this->configured()) {
            return redirect()->route('login')->withErrors([
                'google' => 'Google sign-in is not configured yet. Please use your email and password, or try again later.',
            ]);
        }

        try {
            /** @var GoogleUser $googleUser */
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('login')->withErrors([
                'google' => 'Google sign-in was cancelled or could not be completed. Please try again.',
            ]);
        }

        $email = Str::lower(trim((string) $googleUser->getEmail()));

        if ($email === '' || ! $this->googleEmailIsVerified($googleUser)) {
            return redirect()->route('login')->withErrors([
                'google' => 'Google did not provide a verified email address. Please use a verified Google account.',
            ]);
        }

        $user = $this->userForGoogleEmail($email);

        if ($user) {
            if ($this->accountNeedsApplicationApproval($user)) {
                return redirect()->route('login')->withErrors([
                    'google' => self::PENDING_APPLICATION_MESSAGE,
                ]);
            }

            if (! $user->is_active) {
                return redirect()->route('login')->withErrors([
                    'google' => 'This account has been deactivated. Please contact the office.',
                ]);
            }

            Auth::login($user, false);
            $request->session()->regenerate();
            $user->update(['last_login_at' => now()]);
            $audit->log('login', $user->name.' signed in with Google.', user: $user, request: $request);

            return redirect()->intended($user->homePath());
        }

        if ($this->hasApplicationForEmail($email)) {
            return redirect()->route('login')->withErrors([
                'google' => self::PENDING_APPLICATION_MESSAGE,
            ]);
        }

        return redirect()->route('login')->withErrors([
            'google' => self::UNKNOWN_ACCOUNT_MESSAGE,
        ]);
    }

    private function configured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    private function googleEmailIsVerified(GoogleUser $googleUser): bool
    {
        $raw = $googleUser->user ?? [];

        $verified = $raw['email_verified'] ?? $raw['verified_email'] ?? null;

        if (is_bool($verified)) {
            return $verified;
        }

        if (is_string($verified)) {
            return in_array(Str::lower($verified), ['true', '1', 'yes'], true);
        }

        if (is_int($verified)) {
            return $verified === 1;
        }

        // Google always returns verified_email for the userinfo endpoint.
        // Treat a missing flag as unverified so we never sign in on incomplete payloads.
        return false;
    }

    private function userForGoogleEmail(string $email): ?User
    {
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user) {
            return $user;
        }

        return Applicant::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first()
            ?->user;
    }

    private function accountNeedsApplicationApproval(User $user): bool
    {
        if ($user->pending_account) {
            return true;
        }

        return $user->isApplicant() && ! $user->is_active;
    }

    private function hasApplicationForEmail(string $email): bool
    {
        return Applicant::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereHas('applications')
            ->exists();
    }
}
