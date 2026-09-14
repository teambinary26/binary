<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value', 'group'];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $settings = Cache::remember('system_settings', 60, function () {
            return static::query()->pluck('value', 'key');
        });

        return $settings[$key] ?? $default;
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
