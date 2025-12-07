<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

interface HttpClientInterface
{
    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>|string $payload
     */
    public function postJson(string $url, array|string $payload, array $headers = [], int $timeoutSeconds = 20, int $retries = 1): HttpResponse;

    /**
     * @param array<string, string> $headers
     */
    public function get(string $url, array $headers = [], int $timeoutSeconds = 20, int $retries = 1): HttpResponse;
}
