<?php

declare(strict_types=1);

namespace App\Monitoring;

final class TraceIdGenerator
{
    public function generate(): string
    {
        $bytes = random_bytes(16);
        $hex = bin2hex($bytes);

        // Formato UUIDv4 simple.
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20)
        );
    }
}
