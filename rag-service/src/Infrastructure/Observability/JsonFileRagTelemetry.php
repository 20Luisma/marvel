<?php

declare(strict_types=1);

namespace Creawebes\Rag\Infrastructure\Observability;

use Creawebes\Rag\Application\Contracts\RagTelemetryInterface;

final class JsonFileRagTelemetry implements RagTelemetryInterface
{
    public function __construct(
        private readonly ServerTraceIdProvider $traceIdProvider,
        private readonly string $logFile,
    ) {
    }

    public function log(string $event, string $retriever, int $latencyMs, int $topK): void
    {
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
            'retriever' => $retriever,
            'latency_ms' => $latencyMs,
            'top_k' => $topK,
        ];

        $encoded = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            return;
        }

        @file_put_contents($file, $encoded . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

