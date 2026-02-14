<?php

declare(strict_types=1);

namespace App\Security\Http;

/**
 * Thrown when CSRF validation fails and the request must be terminated.
 * Replaces the raw exit() call for testability and clean architecture.
 */
final class CsrfTerminationException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('CSRF validation failed: invalid or missing token.');
    }
}
