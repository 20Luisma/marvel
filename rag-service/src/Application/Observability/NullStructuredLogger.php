<?php

declare(strict_types=1);

namespace Creawebes\Rag\Application\Observability;

use Creawebes\Rag\Application\Contracts\StructuredLoggerInterface;

final class NullStructuredLogger implements StructuredLoggerInterface
{
    public function log(string $event, array $fields = []): void
    {
    }
}

