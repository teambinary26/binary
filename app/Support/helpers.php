<?php

use App\Enums\ApplicationStatus;
use App\Models\SystemSetting;
use Illuminate\Support\Carbon;

if (! function_exists('peso')) {
    function peso(mixed $amount, bool $blankIfNull = true): string
    {
        if ($amount === null || $amount === '') {
            return $blankIfNull ? '—' : '₱0.00';
        }

        return '₱'.number_format((float) $amount, 2);
    }
}

if (! function_exists('gov_date')) {
    function gov_date(mixed $date, string $format = 'd F Y'): string
    {
        if (! $date) {
            return '—';
        }

        return Carbon::parse($date)->timezone(config('app.timezone'))->format($format);
    }
}

if (! function_exists('gov_datetime')) {
    function gov_datetime(mixed $date): string
    {
        if (! $date) {
            return '—';
        }

        return Carbon::parse($date)->timezone(config('app.timezone'))->format('d F Y, h:i A');
    }
}

if (! function_exists('status_label')) {
    function status_label(ApplicationStatus|string|null $status): string
    {
        if ($status instanceof ApplicationStatus) {
            return $status->label();
        }

        if (! $status) {
            return 'Unknown';
        }

        return ApplicationStatus::tryFrom($status)?->label() ?? str_replace('_', ' ', ucfirst($status));
    }
}

if (! function_exists('gov')) {
    function gov(?string $key = null, mixed $default = null): mixed
    {
        $config = config('cams', []);

        try {
            $stored = SystemSetting::allValues();
            foreach (SystemSetting::IDENTITY_KEYS as $identityKey) {
                $value = $stored[$identityKey] ?? null;
                if ($value !== null && $value !== '') {
                    $config[$identityKey] = $value;
                }
            }
        } catch (Throwable) {
            // Settings may be unavailable before migrations have run.
        }

        if ($key === null) {
            return $config;
        }

        return $config[$key] ?? $default;
    }
}
