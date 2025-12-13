<?php

declare(strict_types=1);

namespace Creawebes\Rag\Application\Contracts;

interface HttpTransportInterface
{
    /**
     * @param array<int, string> $headers
     * @return array{response: string|false, http_code: int, error: string}
     */
    public function post(string $url, array $headers, string $body, int $connectTimeoutSeconds, int $timeoutSeconds): array;
}

