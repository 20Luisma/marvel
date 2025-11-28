<?php

declare(strict_types=1);

namespace App\Security\RateLimit;

final class RateLimitResult
{
    public function __construct(
        public bool $isLimited,
        public int $remaining,
        public int $maxRequests,
        public int $resetAt
    ) {
    }
}
