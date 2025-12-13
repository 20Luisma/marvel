<?php

declare(strict_types=1);

namespace Creawebes\Rag\Application\Observability;

use Creawebes\Rag\Application\Contracts\RagTelemetryInterface;

final class NullRagTelemetry implements RagTelemetryInterface
{
    public function log(string $event, string $retriever, int $latencyMs, int $topK): void
    {
    }
}

