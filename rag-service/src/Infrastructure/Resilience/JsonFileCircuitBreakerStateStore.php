<?php

declare(strict_types=1);

namespace Creawebes\Rag\Infrastructure\Resilience;

use Creawebes\Rag\Application\Resilience\CircuitBreakerStateStoreInterface;
use ErrorException;
use Throwable;

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

        $handle = null;
        try {
            set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
                throw new ErrorException($message, 0, $severity, $file, $line);
            });

            $handle = fopen($path, 'rb');
            if ($handle === false) {
                return $default;
            }

            flock($handle, LOCK_SH);
            $contents = stream_get_contents($handle);
            flock($handle, LOCK_UN);
        } catch (Throwable) {
            return $default;
        } finally {
            restore_error_handler();
            if (is_resource($handle)) {
                fclose($handle);
            }
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
        $payload = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            return;
        }

        try {
            set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
                throw new ErrorException($message, 0, $severity, $file, $line);
            });

            if (!is_dir($directory)) {
                if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
                    return;
                }
            }

            file_put_contents($path, $payload, LOCK_EX);
        } catch (Throwable) {
            return;
        } finally {
            restore_error_handler();
        }
    }
}
