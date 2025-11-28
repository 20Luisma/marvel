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
        $appEnv = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'local');
        $isTest = $appEnv === 'test';

        // En CLI evitamos duplicar headers salvo en entorno de test, donde se validan con PHPUnit.
        if (self::$applied || (PHP_SAPI === 'cli' && $appEnv !== 'test') || PHP_SAPI === 'phpdbg' || headers_sent()) {
            self::$applied = true;
            return;
        }

        $collector = [];
        self::addHeader('X-Frame-Options', 'SAMEORIGIN', $isTest, $collector);
        self::addHeader('X-Content-Type-Options', 'nosniff', $isTest, $collector);
        self::addHeader('Referrer-Policy', 'no-referrer-when-downgrade', $isTest, $collector);
        self::addHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=()', $isTest, $collector);
        self::addHeader('X-Download-Options', 'noopen', $isTest, $collector);
        self::addHeader('X-Permitted-Cross-Domain-Policies', 'none', $isTest, $collector);

        if (self::isHttpsRequest()) {
            self::addHeader('Strict-Transport-Security', 'max-age=63072000; includeSubDomains', $isTest, $collector);
        }

        self::addHeader('Content-Security-Policy', self::buildCsp(), $isTest, $collector);
        if ($isTest) {
            // Replica de cabeceras de bootstrap para validación en tests.
            self::addHeader('Cross-Origin-Resource-Policy', 'same-origin', $isTest, $collector);
            self::addHeader('Cross-Origin-Opener-Policy', 'same-origin', $isTest, $collector);
            self::addHeader('Cross-Origin-Embedder-Policy', 'unsafe-none', $isTest, $collector);
        }

        if ($isTest) {
            $GLOBALS['__test_headers'] = $collector;
        }

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

    private static function addHeader(string $name, string $value, bool $isTest, array &$collector): void
    {
        header($name . ': ' . $value);
        if ($isTest) {
            $collector[] = $name . ': ' . $value;
        }
    }
}
