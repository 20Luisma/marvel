<?php

declare(strict_types=1);

namespace Creawebes\Rag\Infrastructure\Observability;

use Creawebes\Rag\Application\Contracts\StructuredLoggerInterface;
use ErrorException;
use RuntimeException;
use Throwable;

final class JsonFileStructuredLogger implements StructuredLoggerInterface
{
    public function __construct(
        private readonly ServerTraceIdProvider $traceIdProvider,
        private readonly string $logFile,
        private readonly bool $enabled = true,
    ) {
    }

    public function log(string $event, array $fields = []): void
    {
        if ($this->enabled === false) {
            return;
        }

        $file = trim($this->logFile);
        if ($file === '') {
            return;
        }

        $level = 'INFO';
        $normalizedEvent = strtolower($event);
        if (
            str_contains($normalizedEvent, 'error') ||
            str_contains($normalizedEvent, 'exception') ||
            str_contains($normalizedEvent, 'failed') ||
            str_contains($normalizedEvent, 'fail')
        ) {
            $level = 'ERROR';
        } elseif (
            str_contains($normalizedEvent, 'warn') ||
            str_contains($normalizedEvent, 'rate_limit') ||
            str_contains($normalizedEvent, 'csrf') ||
            str_contains($normalizedEvent, 'short_circuit') ||
            str_contains($normalizedEvent, 'circuit.opened')
        ) {
            $level = 'WARN';
        }

        $entry = [
            'timestamp' => date('c'),
            'level' => $level,
            'trace_id' => $this->traceIdProvider->getTraceId(),
            'event' => $event,
        ] + $fields;

        $encoded = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            return;
        }

        $directory = dirname($file);

        try {
            set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
                throw new ErrorException($message, 0, $severity, $file, $line);
            });

            if (!is_dir($directory)) {
                if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
                    throw new RuntimeException('Cannot create dir: ' . $directory);
                }
            }

            $bytes = file_put_contents($file, $encoded . PHP_EOL, FILE_APPEND | LOCK_EX);
            if ($bytes === false) {
                throw new RuntimeException('Cannot write log file: ' . $file);
            }
        } catch (Throwable $e) {
            error_log('[rag-service][log_write_failed] ' . $e->getMessage());
            error_log('[rag-service][log_payload] ' . $encoded);
        } finally {
            restore_error_handler();
        }
    }
}
