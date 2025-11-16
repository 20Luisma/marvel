<?php

declare(strict_types=1);

use Src\Shared\Http\Router;

require_once __DIR__ . '/../vendor/autoload.php';

$container = require_once __DIR__ . '/../src/bootstrap.php';

if (!function_exists('route')) {
    /**
     * @param array<string, mixed> $container
     */
    function route(string $method, string $path, array $container): void
    {
        (new Router($container))->handle($method, $path);
    }
}

if (!defined('SKIP_HTTP_BOOT')) {
    if (!defined('ALBUM_UPLOAD_DIR')) {
        define('ALBUM_UPLOAD_DIR', __DIR__ . '/uploads/albums');
    }
    if (!defined('ALBUM_UPLOAD_URL_PREFIX')) {
        define('ALBUM_UPLOAD_URL_PREFIX', '/uploads/albums/');
    }
    if (!defined('ALBUM_COVER_MAX_BYTES')) {
        define('ALBUM_COVER_MAX_BYTES', 5 * 1024 * 1024);
    }

    $allowedOrigin = trim((string) ($_ENV['APP_ORIGIN'] ?? $_ENV['APP_URL'] ?? ''));
    $requestOrigin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');

    $csp = [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdn.jsdelivr.net",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.tailwindcss.com",
        "font-src 'self' https://fonts.gstatic.com https://r2cdn.perplexity.ai data:",
        "img-src 'self' data: blob: https:",
        "media-src 'self' data: blob: https:",
        "connect-src 'self' https: https://sentry.io http://localhost:8080 http://localhost:8081 http://localhost:8082",
        "frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com",
        "frame-ancestors 'self'",
    ];

    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: microphone=(), camera=(), geolocation=()');
    header('Content-Security-Policy: ' . implode('; ', $csp));

    if ($allowedOrigin !== '' && $requestOrigin !== '') {
        if ($allowedOrigin === $requestOrigin) {
            header('Access-Control-Allow-Origin: ' . $allowedOrigin);
            header('Vary: Origin');
        } else {
            http_response_code(403);
            exit;
        }
    }

    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if ($path === '/home.php') {
        $path = '/';
    }

    (new Router($container))->handle($method, $path);
}
