<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Resilience;

use RuntimeException;

/**
 * Exception thrown when the circuit breaker is open and a fallback is not available.
 */
final class CircuitBreakerOpenException extends RuntimeException
{
    public function __construct(string $message = 'Circuit breaker is open', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
