<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Shared\Infrastructure\Http\HttpClientInterface;
use App\Shared\Infrastructure\Http\HttpResponse;

final class HttpClientStub implements HttpClientInterface
{
    public array $requests = [];
    public int $statusCode = 200;
    public string $body = '{}';

    /**
     * @param array<string, string> $headers
     */
    public function postJson(string $url, array|string $payload, array $headers = [], int $timeoutSeconds = 20, int $retries = 1): HttpResponse
    {
        $this->requests[] = [
            'url' => $url,
            'payload' => $payload,
            'headers' => $headers,
            'timeout' => $timeoutSeconds,
            'retries' => $retries,
        ];

        return new HttpResponse($this->statusCode, $this->body);
    }
}
