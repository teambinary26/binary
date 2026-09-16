<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    public const IDENTITY_KEYS = [
        'agency',
        'lgu',
        'province',
        'address',
        'phone',
        'email',
        'office_hours',
    ];

    protected $fillable = ['key', 'value', 'group'];

    public static function allValues(): array
    {
        $settings = Cache::remember('system_settings', 60, function () {
            return static::query()->pluck('value', 'key')->all();
        });

        if ($settings instanceof \Illuminate\Support\Collection) {
            return $settings->all();
        }

        return is_array($settings) ? $settings : [];
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        return static::allValues()[$key] ?? $default;
    }

    public static function setValue(string $key, mixed $value, string $group = 'general'): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'group' => $group]
        );

        Cache::forget('system_settings');
    }
}
