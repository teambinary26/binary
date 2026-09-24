<?php

namespace App\Support;

class IdRequirements
{
    public static function requiresBack(?string $name): bool
    {
        return (bool) preg_match('/\bID\b/i', (string) $name);
    }
}
