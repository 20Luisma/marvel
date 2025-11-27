<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

interface HttpClientInterface
{
    /**
     * @param array<string, string> $headers
     */
    public function postJson(string $url, array $payload, array $headers = [], int $timeoutSeconds = 20, int $retries = 1): HttpResponse;
}
