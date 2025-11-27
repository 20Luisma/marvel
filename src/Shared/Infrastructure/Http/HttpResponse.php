<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

final class HttpResponse
{
    public function __construct(
        public readonly int $statusCode,
        public readonly string $body
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }
}
