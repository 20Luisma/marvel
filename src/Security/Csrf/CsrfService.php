<?php

declare(strict_types=1);

namespace App\Security\Csrf;

final class CsrfService
{
    private const SESSION_KEY = '_csrf_token';
    private const ALT_SESSION_KEY = 'csrf_token';

    public static function generateToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        // Mantener ambas claves para compatibilidad con CsrfTokenManager.
        $_SESSION[self::ALT_SESSION_KEY] = $_SESSION[self::SESSION_KEY];

        return (string) $_SESSION[self::SESSION_KEY];
    }

    public static function validateToken(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!is_string($token) || $token === '') {
            return false;
        }

        $stored = $_SESSION[self::SESSION_KEY] ?? $_SESSION[self::ALT_SESSION_KEY] ?? null;
        if (!is_string($stored) || $stored === '') {
            return false;
        }

        return hash_equals($stored, $token);
    }
}
