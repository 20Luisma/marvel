<?php

declare(strict_types=1);

namespace App\Security\Http;

final class CsrfTokenManager
{
    private const SESSION_KEY = '_csrf_token';

    public function __construct(private readonly string $appEnvironment = 'local')
    {
    }

    public function generate(): string
    {
        $this->ensureSession();

        if (!isset($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION[self::SESSION_KEY];
    }

    public function validate(?string $token): bool
    {
        if ($this->shouldBypass()) {
            return true;
        }

        $this->ensureSession();

        if (!isset($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            return false;
        }

        if (!is_string($token) || $token === '') {
            return false;
        }

        return hash_equals($_SESSION[self::SESSION_KEY], $token);
    }

    private function shouldBypass(): bool
    {
        return $this->appEnvironment === 'test';
    }

    private function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
