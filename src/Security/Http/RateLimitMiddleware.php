<?php

declare(strict_types=1);

namespace App\Security\Http;

use App\Security\Logging\SecurityLogger;
use App\Security\RateLimit\RateLimitResult;
use App\Security\RateLimit\RateLimiter;
use App\Controllers\Http\Request;

final class RateLimitMiddleware
{
    /**
     * @param array<string, array{max: int, window: int}> $routeLimits
     */
    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly array $routeLimits = [],
        private readonly ?SecurityLogger $logger = null
    ) {
    }

    public function handle(string $method, string $path): bool
    {
        if (!$this->shouldProtect($method, $path)) {
            return true;
        }

        $ip = $this->clientIp();
        $result = $this->limiter->hit($ip, $path);

        if ($result->isLimited) {
        $this->logLimited($ip, $path, $result);
        $this->respondLimited($path, $result);
        return false;
        }

        return true;
    }

    private function shouldProtect(string $method, string $path): bool
    {
        $protected = [
            '/login',
            '/api/rag/heroes',
            '/agentia',
            '/secret-heatmap',
            '/panel-github',
            '/panel-performance',
            '/panel-accessibility',
            '/panel-repo-marvel',
            '/performance',
        ];

        // Usa rutas configuradas explícitamente como protegidas.
        foreach (array_keys($this->routeLimits) as $route) {
            $protected[] = $route;
        }

        if (!in_array($path, $protected, true)) {
            return false;
        }

        if ($path === '/login' && $method !== 'POST') {
            return false;
        }

        if ($path === '/api/rag/heroes' && $method !== 'POST') {
            return false;
        }

        return true;
    }

    private function clientIp(): string
    {
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;
        if (is_string($forwarded) && trim($forwarded) !== '') {
            $parts = explode(',', $forwarded);
            return trim($parts[0]);
        }

        $remote = $_SERVER['REMOTE_ADDR'] ?? '';
        return is_string($remote) && $remote !== '' ? $remote : 'unknown';
    }

    private function respondLimited(string $path, RateLimitResult $result): void
    {
        http_response_code(429);
        if (str_starts_with($path, '/api/')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'error' => 'rate_limited',
                'message' => 'Too many requests, try again later.',
                'reset_at' => $result->resetAt,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        if (!Request::wantsHtml()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'error' => 'rate_limited',
                'message' => 'Too many requests, try again later.',
                'reset_at' => $result->resetAt,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html><body><h1>429</h1><p>Demasiadas peticiones, intenta de nuevo en unos segundos.</p></body></html>';
    }

    private function logLimited(string $ip, string $path, RateLimitResult $result): void
    {
        if ($this->logger === null) {
            return;
        }

        $this->logger->logEvent('rate_limit', [
            'trace_id' => $_SERVER['X_TRACE_ID'] ?? null,
            'ip' => $ip,
            'path' => $path,
            'status' => 429,
            'max' => $result->maxRequests,
            'remaining' => $result->remaining,
            'reset_at' => $result->resetAt,
        ]);
    }
}
