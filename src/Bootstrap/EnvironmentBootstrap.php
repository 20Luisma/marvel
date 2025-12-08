<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Monitoring\TraceIdGenerator;

final class EnvironmentBootstrap
{
    public static function initialize(): void
    {
        $rootPath = dirname(__DIR__, 2);
        $envPath = $rootPath . DIRECTORY_SEPARATOR . '.env';

        $skipDotEnv = (getenv('APP_ENV') === 'test') || (($_ENV['APP_ENV'] ?? null) === 'test');
        if (!$skipDotEnv && is_file($envPath)) {
            foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                if (str_starts_with($line, '#')) {
                    continue;
                }

                [$key, $value] = array_map('trim', explode('=', $line, 2) + [1 => '']);

                if ($key !== '') {
                    $_ENV[$key] = $value;
                    putenv($key . '=' . $value);
                }
            }
        }

        $traceGenerator = new TraceIdGenerator();
        $traceId = $_SERVER['X_TRACE_ID'] ?? null;
        if (!is_string($traceId) || trim($traceId) === '') {
            $traceId = $traceGenerator->generate();
            $_SERVER['X_TRACE_ID'] = $traceId;
        }
        header('X-Trace-Id: ' . $traceId);

        if (session_status() === PHP_SESSION_NONE) {
            $cookieParams = session_get_cookie_params();
            $isSecure = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1'))
                || ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443);

            session_set_cookie_params([
                'lifetime' => 0,
                'path' => $cookieParams['path'],
                'domain' => $cookieParams['domain'],
                'secure' => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);

            session_start();
        }
    }
}
