<?php

declare(strict_types=1);

namespace App\Security\Http;

/**
 * Security headers middleware: aplica cabeceras HTTP básicas en un único punto.
 */
final class SecurityHeaders
{
    private static bool $applied = false;

    public static function apply(): void
    {
        if (self::$applied || PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg' || headers_sent()) {
            self::$applied = true;
            return;
        }

        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer-when-downgrade');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

        if (self::isHttpsRequest()) {
            header('Strict-Transport-Security: max-age=63072000; includeSubDomains');
        }

        header('Content-Security-Policy: ' . self::buildCsp());

        self::$applied = true;
    }

    private static function isHttpsRequest(): bool
    {
        if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1')) {
            return true;
        }

        return isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443';
    }

    private static function buildCsp(): string
    {
        $directives = [
            "default-src 'self'",
            "img-src 'self' data: blob: https:",
            "media-src 'self' data: blob: https:",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.tailwindcss.com",
            "font-src 'self' https://fonts.gstatic.com https://r2cdn.perplexity.ai data:",
            "script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdn.jsdelivr.net",
            "connect-src 'self' https: http://localhost:8080 http://localhost:8081 http://localhost:8082",
            "frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com",
            "frame-ancestors 'self'",
        ];

        // Nota: CSP básica para no romper assets actuales (Tailwind CDN + fuentes). TODO: endurecer con nonce/SRI.
        return implode('; ', $directives);
    }
}
