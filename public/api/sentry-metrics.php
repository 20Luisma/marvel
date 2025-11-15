<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use RuntimeException;
use Throwable;

$rootPath = dirname(__DIR__, 2);
$vendorAutoload = $rootPath . '/vendor/autoload.php';

if (is_file($vendorAutoload)) {
    require_once $vendorAutoload;
}

// Carga de .env (compatible con Dotenv o fallback manual)
if (class_exists(Dotenv::class)) {
    Dotenv::createImmutable($rootPath)->safeLoad();
} else {
    $envFile = $rootPath . '/.env';
    if (is_file($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$name, $value] = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value, " \t\n\r\0\x0B\"'");
                if ($name !== '') {
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                    putenv($name . '=' . $value);
                }
            }
        }
    }
}

header('Content-Type: application/json');
header('Cache-Control: no-store, max-age=0');

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    jsonErrorResponse(405, 'Método no permitido. Usa GET para consultar el panel de Sentry.');
}

$org = envValue('SENTRY_ORG');
$project = envValue('SENTRY_PROJECT');
$token = envValue('SENTRY_TOKEN');
$ttl = (int) envValue('SENTRY_CACHE_TTL', '600');
$ttl = $ttl > 0 ? $ttl : 600;

if ($org === '' || $project === '' || $token === '') {
    jsonErrorResponse(
        500,
        'Configura SENTRY_ORG, SENTRY_PROJECT y SENTRY_TOKEN en tu entorno para consultar Sentry.'
    );
}

$cacheFile = $rootPath . '/storage/sentry-cache.json';
$cachePayload = readCache($cacheFile);

if ($cachePayload !== null && !cacheExpired($cachePayload, $cacheFile, $ttl)) {
    jsonResponse(200, [
        'source' => 'cache',
        'data' => $cachePayload,
    ]);
}

try {
    $endpoint = sprintf(
        'https://sentry.io/api/0/projects/%s/%s/issues/',
        rawurlencode($org),
        rawurlencode($project)
    );

    $handle = curl_init($endpoint);
    if ($handle === false) {
        throw new RuntimeException('No se pudo iniciar la solicitud a Sentry.');
    }

    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $response = curl_exec($handle);
    $statusCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $curlError = curl_error($handle);
    curl_close($handle);

    if ($response === false) {
        throw new RuntimeException('No se pudo contactar Sentry: ' . ($curlError ?: 'Error desconocido.'));
    }

    if ($statusCode < 200 || $statusCode >= 300) {
        throw new RuntimeException('Sentry devolvió un error (HTTP ' . $statusCode . ').');
    }

    $issues = json_decode($response, true);
    if (!is_array($issues)) {
        throw new RuntimeException('La respuesta de Sentry no tiene el formato esperado.');
    }

    $data = [
        'cached_at' => date('c'),
        'count' => count($issues),
        'issues' => $issues,
    ];

    @file_put_contents(
        $cacheFile,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );

    jsonResponse(200, [
        'source' => 'live',
        'data' => $data,
    ]);
} catch (Throwable $exception) {
    if ($cachePayload !== null) {
        jsonResponse(200, [
            'source' => 'cache-fallback',
            'warning' => 'Error consultando Sentry. Usando últimos datos guardados.',
            'data' => $cachePayload,
        ]);
    }

    jsonResponse(200, [
        'source' => 'empty',
        'warning' => 'No se pudo consultar Sentry y no existe cache previo.',
        'data' => [],
    ]);
}

/**
 * @return array<string, mixed>|null
 */
function readCache(string $cacheFile): ?array
{
    if (!is_file($cacheFile)) {
        return null;
    }

    $contents = file_get_contents($cacheFile);
    if ($contents === false || $contents === '') {
        return null;
    }

    $decoded = json_decode($contents, true);
    return is_array($decoded) ? $decoded : null;
}

function cacheExpired(array $cache, string $cacheFile, int $ttl): bool
{
    $cachedAt = strtolower((string) ($cache['cached_at'] ?? ''));
    $cachedTimestamp = is_string($cachedAt) ? strtotime($cachedAt) : false;

    if ($cachedTimestamp === false) {
        $cachedTimestamp = filemtime($cacheFile) ?: 0;
    }

    return ($cachedTimestamp + $ttl) < time();
}

function envValue(string $key, string $default = ''): string
{
    return trim((string) (getenv($key) ?: ($_ENV[$key] ?? $default)));
}

/**
 * @param array<string, mixed> $payload
 */
function jsonResponse(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function jsonErrorResponse(int $status, string $message): void
{
    jsonResponse($status, [
        'error' => $message,
    ]);
}
