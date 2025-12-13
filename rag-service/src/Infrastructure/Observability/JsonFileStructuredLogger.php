<?php

declare(strict_types=1);

namespace Creawebes\Rag\Infrastructure\Observability;

use Creawebes\Rag\Application\Contracts\StructuredLoggerInterface;

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

        $directory = dirname($file);
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $entry = [
            'timestamp' => date('c'),
            'trace_id' => $this->traceIdProvider->getTraceId(),
            'event' => $event,
        ] + $fields;

        $encoded = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            return;
        }

        @file_put_contents($file, $encoded . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

