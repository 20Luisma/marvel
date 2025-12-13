<?php

declare(strict_types=1);

namespace Creawebes\Rag\Application\Contracts;

interface RagTelemetryInterface
{
    public function log(string $event, string $retriever, int $latencyMs, int $topK): void;
}

