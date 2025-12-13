<?php

declare(strict_types=1);

namespace Creawebes\Rag\Application\Contracts;

interface StructuredLoggerInterface
{
    /**
     * @param array<string, mixed> $fields
     */
    public function log(string $event, array $fields = []): void;
}

