<?php

declare(strict_types=1);

namespace App\Bootstrap\Shared;

final class DriverResolver
{
    public static function resolve(string $envKey, string $appEnv, string $default = 'db'): string
    {
        $raw = $_ENV[$envKey] ?? getenv($envKey);
        if (is_string($raw) && trim($raw) !== '') {
            $normalized = strtolower(trim($raw));
            return in_array($normalized, ['db', 'file'], true) ? $normalized : $default;
        }

        return $appEnv === 'test' ? 'file' : $default;
    }
}
