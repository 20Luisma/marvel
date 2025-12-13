<?php

declare(strict_types=1);

if (!array_key_exists('MARVEL_RAW_BODY', $_SERVER)) {
    $raw = file_get_contents('php://input');
    $_SERVER['MARVEL_RAW_BODY'] = $raw === false ? '' : $raw;
}

use App\Config\ServiceUrlProvider;
use App\Shared\Infrastructure\Http\HttpClientInterface;
use App\Security\RateLimit\RateLimiter;
use App\Security\Logging\SecurityLogger;
use App\Controllers\RagProxyController;
use App\Heroes\Domain\Repository\HeroRepository;

require_once dirname(__DIR__, 4) . '/vendor/autoload.php';

$container = require_once dirname(__DIR__, 4) . '/src/bootstrap.php';

$limiter = $container['security']['rateLimiter'] ?? null;
$securityLogger = $container['security']['logger'] ?? null;
if ($limiter instanceof RateLimiter) {
    $ip = is_string($_SERVER['REMOTE_ADDR'] ?? null) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
    $result = $limiter->hit($ip, '/api/rag/heroes');
    if ($result->isLimited) {
        if ($securityLogger instanceof SecurityLogger) {
            $securityLogger->logEvent('rate_limit', [
                'trace_id' => $_SERVER['X_TRACE_ID'] ?? null,
                'ip' => $ip,
                'path' => '/api/rag/heroes',
                'status' => 429,
                'max' => $result->maxRequests,
                'remaining' => $result->remaining,
                'reset_at' => $result->resetAt,
            ]);
        } else {
            log_security_event($ip, '/api/rag/heroes', $result);
        }
        http_response_code(429);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'error' => 'rate_limited',
            'message' => 'Too many requests, try again later.',
            'reset_at' => $result->resetAt,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

$http = $container['http']['client'] ?? null;
if (!$http instanceof HttpClientInterface) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Cliente HTTP no disponible.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$ragUrl = resolve_rag_url($container);
$token = $container['security']['internal_api_key'] ?? null;
$token = is_string($token) ? $token : null;

// Intento preventivo de sincronizar los héroes al RAG antes de comparar (por si el upsert previo falló en hosting).
try {
    $rawBody = $_SERVER['MARVEL_RAW_BODY'] ?? '';
    $payload = is_string($rawBody) && $rawBody !== '' ? json_decode($rawBody, true) : null;
    if (is_array($payload) && isset($payload['heroIds']) && is_array($payload['heroIds'])) {
        $heroIds = array_values(array_filter(array_map('strval', $payload['heroIds']), static fn($id) => trim($id) !== ''));
        if (count($heroIds) === 2) {
            $heroRepository = $container['heroRepository'] ?? null;
            $ragSyncer = $container['services']['ragSyncer'] ?? null;
            if ($heroRepository instanceof HeroRepository && $ragSyncer && method_exists($ragSyncer, 'sync')) {
                foreach ($heroIds as $heroId) {
                    $hero = $heroRepository->find($heroId);
                    if ($hero !== null) {
                        $ragSyncer->sync($hero);
                    }
                }
            }
        }
    }
} catch (\Throwable $e) {
    // No bloqueamos la petición; seguimos adelante.
}

$controller = new RagProxyController($http, $ragUrl, $token);
$controller->forwardHeroesComparison();

/**
 * @param string $ip
 */
function log_security_event(string $ip, string $path, \App\Security\RateLimit\RateLimitResult $result): void
{
    $logFile = dirname(__DIR__, 4) . '/storage/logs/security.log';
    $directory = dirname($logFile);
    if (!is_dir($directory)) {
        @mkdir($directory, 0775, true);
    }

    $traceId = $_SERVER['X_TRACE_ID'] ?? null;
    $line = sprintf(
        '[%s] trace_id=%s ip=%s path=%s status=429 max=%d remaining=%d reset_at=%d',
        date('Y-m-d H:i:s'),
        $traceId ?: '-',
        $ip,
        $path,
        $result->maxRequests,
        $result->remaining,
        $result->resetAt
    );

    @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND);
}

function resolve_rag_url(array $container): string
{
    $configured = $_ENV['RAG_SERVICE_URL'] ?? getenv('RAG_SERVICE_URL') ?: null;
    if (is_string($configured) && trim($configured) !== '') {
        $base = rtrim($configured, '/');
        if (str_contains($base, '/rag/agent')) {
            $base = str_replace('/rag/agent', '/rag/heroes', $base);
        } elseif (!str_contains($base, '/rag/heroes')) {
            $base .= '/rag/heroes';
        }
        return $base;
    }

    $provider = $container['services']['urlProvider'] ?? null;
    if ($provider instanceof ServiceUrlProvider) {
        return $provider->getRagHeroesUrl();
    }

    return 'http://localhost:8082/rag/heroes';
}
