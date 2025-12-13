<?php

declare(strict_types=1);

namespace Creawebes\Rag\Infrastructure\Resilience;

use Creawebes\Rag\Application\Resilience\CircuitBreakerStateStoreInterface;

final class JsonFileCircuitBreakerStateStore implements CircuitBreakerStateStoreInterface
{
    public function __construct(private readonly string $filePath)
    {
    }

    public function load(): array
    {
        $default = [
            'state' => 'closed',
            'failure_count' => 0,
            'opened_at' => 0,
            'half_open_calls' => 0,
        ];

        $path = trim($this->filePath);
        if ($path === '') {
            return $default;
        }

        if (!is_file($path)) {
            return $default;
        }

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return $default;
        }

        try {
            @flock($handle, LOCK_SH);
            $contents = stream_get_contents($handle);
        } finally {
            @flock($handle, LOCK_UN);
            fclose($handle);
        }

        if (!is_string($contents) || trim($contents) === '') {
            return $default;
        }

        $decoded = json_decode($contents, true);
        if (!is_array($decoded)) {
            return $default;
        }

        return [
            'state' => isset($decoded['state']) && is_string($decoded['state']) ? $decoded['state'] : $default['state'],
            'failure_count' => isset($decoded['failure_count']) ? (int) $decoded['failure_count'] : 0,
            'opened_at' => isset($decoded['opened_at']) ? (int) $decoded['opened_at'] : 0,
            'half_open_calls' => isset($decoded['half_open_calls']) ? (int) $decoded['half_open_calls'] : 0,
        ];
    }

    public function save(array $state): void
    {
        $path = trim($this->filePath);
        if ($path === '') {
            return;
        }

        $directory = dirname($path);
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $payload = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            return;
        }

        @file_put_contents($path, $payload, LOCK_EX);
    }
}

