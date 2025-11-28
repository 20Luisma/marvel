<?php

declare(strict_types=1);

namespace App\Security\Validation;

final class InputSanitizer
{
    public function sanitizeString(string $value, int $maxLength = 1000): string
    {
        $clean = trim($value);
        $clean = preg_replace('/\s+/', ' ', $clean) ?? $clean;
        $clean = preg_replace('#<[^>]+>#', '', $clean) ?? $clean;
        if (mb_strlen($clean) > $maxLength) {
            $clean = mb_substr($clean, 0, $maxLength);
        }

        return $clean;
    }

    public function isSuspicious(string $value): bool
    {
        $haystack = strtolower($value);
        $patterns = [
            '<script',
            'onerror=',
            'onload=',
            'drop table',
            'union select',
            'select * from',
            '"\'--',
        ];

        foreach ($patterns as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
