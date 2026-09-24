<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
        'verify_url' => env('TURNSTILE_VERIFY_URL', 'https://challenges.cloudflare.com/turnstile/v0/siteverify'),
    ],

    'semaphore' => [
        'key' => env('SEMAPHORE_API_KEY'),
        'url' => env('SEMAPHORE_URL', 'https://api.semaphore.co/api/v4/messages'),
        'sender' => env('SEMAPHORE_SENDER', ''),
    ],

    'ocrspace' => [
        'key' => env('OCRSPACE_API_KEY'),
        'url' => env('OCRSPACE_URL', 'https://api.ocr.space/parse/image'),
        'engine' => env('OCRSPACE_ENGINE', '2'),
        'timeout' => (int) env('OCRSPACE_TIMEOUT', 60),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => (static function () {
            $fallback = rtrim((string) env('APP_URL', 'http://localhost'), '/').'/login/google/callback';
            $redirect = trim((string) env('GOOGLE_REDIRECT_URI', ''));

            if ($redirect === '' || str_contains($redirect, '{APP_URL}') || ! filter_var($redirect, FILTER_VALIDATE_URL)) {
                return $fallback;
            }

            return $redirect;
        })(),
    ],

];
