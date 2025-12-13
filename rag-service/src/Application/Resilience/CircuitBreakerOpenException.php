<?php

declare(strict_types=1);

namespace Creawebes\Rag\Application\Resilience;

use RuntimeException;

final class CircuitBreakerOpenException extends RuntimeException
{
    public function __construct(string $state)
    {
        parent::__construct('Servicio de IA temporalmente no disponible (circuit breaker: ' . $state . ').');
    }
}

