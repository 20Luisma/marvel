<?php

declare(strict_types=1);

namespace App\Security;

final class LogSanitizer
{
    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public static function sanitizeContext(array $context): array
    {
        $sanitized = [];
        foreach ($context as $key => $value) {
            $lowerKey = strtolower((string) $key);
            if (self::isSensitiveKey($lowerKey)) {
                $sanitized[$key] = self::maskValue($value);
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = self::sanitizeContext($value);
                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $sensitive = [
            'password',
            'pass',
            'pwd',
            'token',
            'access_token',
            'refresh_token',
            'api_key',
            'apikey',
            'key',
            'authorization',
            'auth_header',
        ];

        return in_array($key, $sensitive, true);
    }

    private static function maskValue(mixed $value): string
    {
        if (!is_string($value)) {
            return '***redacted***';
        }

        $length = strlen($value);
        if ($length <= 8) {
            return '***redacted***';
        }

        $prefix = substr($value, 0, 4);
        $suffix = substr($value, -4);

        return sprintf('%s...%s (redacted)', $prefix, $suffix);
    }
}
