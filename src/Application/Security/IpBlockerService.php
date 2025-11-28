<?php

declare(strict_types=1);

namespace App\Application\Security;

use App\Security\Logging\SecurityLogger;

/**
 * Servicio ligero para bloquear IPs/emails reutilizando el almacenamiento de intentos de login.
 */
final class IpBlockerService
{
    public function __construct(
        private readonly LoginAttemptService $loginAttemptService,
        private readonly ?SecurityLogger $logger = null
    ) {
    }

    public function check(string $email, string $ip): bool
    {
        if (!$this->loginAttemptService->isBlocked($email, $ip)) {
            return true;
        }

        $this->logLoginBlocked($email, $ip);

        return false;
    }

    public function registerFailedAttempt(string $email, string $ip): void
    {
        $this->loginAttemptService->registerFailedAttempt($email, $ip);
    }

    public function registerSuccessfulLogin(string $email, string $ip): void
    {
        $this->loginAttemptService->clearAttempts($email, $ip);
    }

    public function getBlockMinutesRemaining(string $email, string $ip): int
    {
        return $this->loginAttemptService->getBlockMinutesRemaining($email, $ip);
    }

    public function getRemainingAttempts(string $email, string $ip): int
    {
        return $this->loginAttemptService->getRemainingAttempts($email, $ip);
    }

    public function logLoginBlocked(string $email, string $ip): void
    {
        if ($this->logger === null) {
            return;
        }

        $this->logger->logEvent('login_blocked', [
            'trace_id' => $_SERVER['X_TRACE_ID'] ?? null,
            'email' => $email,
            'ip' => $ip,
            'path' => $_SERVER['REQUEST_URI'] ?? '/login',
            'blocked_minutes' => $this->getBlockMinutesRemaining($email, $ip),
        ]);
    }
}
