<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TurnstileVerifier
{
    public const SESSION_KEY = 'turnstile.verified_until';

    public static function passed(Request $request): bool
    {
        return (int) $request->session()->get(self::SESSION_KEY, 0) >= now()->timestamp;
    }

    public static function markPassed(Request $request): void
    {
        $request->session()->put(self::SESSION_KEY, now()->addMinutes(30)->timestamp);
    }

    public static function clear(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    public static function canBypass(?Request $request = null): bool
    {
        $request ??= request();

        if (! app()->environment('local')) {
            return false;
        }

        $host = (string) $request->getHost();

        if (! filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        if (in_array($host, ['127.0.0.1', '::1'], true)) {
            return false;
        }

        return str_starts_with($host, '10.')
            || str_starts_with($host, '192.168.')
            || (bool) preg_match('/^172\.(1[6-9]|2\d|3[0-1])\./', $host);
    }

    public static function assertPassed(Request $request, ?string $token = null): void
    {
        if (self::canBypass($request) || self::passed($request)) {
            self::markPassed($request);

            return;
        }

        if (! self::verify($token, $request->ip())) {
            throw ValidationException::withMessages([
                'turnstile_token' => 'Please complete the security check before continuing.',
            ]);
        }

        self::markPassed($request);
    }

    /**
     * POST the token to Cloudflare's siteverify endpoint.
     */
    public static function verify(?string $token, ?string $ip = null): bool
    {
        $secret = (string) config('services.turnstile.secret_key');

        if ($secret === '') {
            Log::warning('Turnstile secret key is not configured; rejecting token.');

            return false;
        }

        if (! is_string($token) || trim($token) === '') {
            return false;
        }

        try {
            $payload = [
                'secret' => $secret,
                'response' => $token,
                'idempotency_key' => (string) Str::uuid(),
            ];

            if (is_string($ip) && $ip !== '') {
                $payload['remoteip'] = $ip;
            }

            $response = Http::asForm()
                ->acceptJson()
                ->timeout(8)
                ->post((string) config('services.turnstile.verify_url'), $payload);

            if (! $response->ok()) {
                Log::warning('Turnstile verification HTTP failure', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            $data = $response->json() ?? [];

            if (empty($data['success'])) {
                Log::info('Turnstile rejected token', [
                    'errors' => $data['error-codes'] ?? [],
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $exception) {
            report($exception);

            return false;
        }
    }
}
