<?php

declare(strict_types=1);

use RuntimeException;

$rootPath = dirname(__DIR__, 2);
require_once $rootPath . '/vendor/autoload.php';
require_once $rootPath . '/src/bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, max-age=0');

$token = envValue('SENTRY_API_TOKEN');
$org = envValue('SENTRY_ORG_SLUG');
$project = envValue('SENTRY_PROJECT_SLUG');
$cacheFile = $rootPath . '/storage/sentry-metrics.json';

if ($token === '' || $org === '' || $project === '') {
    respondWithCacheOrEmpty(
        $cacheFile,
        'Falta SENTRY_API_TOKEN, SENTRY_ORG_SLUG o SENTRY_PROJECT_SLUG para consultar Sentry.'
    );
}

$endpoint = sprintf(
    'https://sentry.io/api/0/projects/%s/%s/issues/?statsPeriod=24h&per_page=5',
    rawurlencode($org),
    rawurlencode($project)
);

try {
    $response = callSentry($endpoint, $token);
    $issues = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

    if (!is_array($issues)) {
        throw new RuntimeException('La respuesta de Sentry no tiene el formato esperado.');
    }

    $normalized = normalizeIssues($issues, $org, $project);
    $status = computeStatus($normalized['errors']);

    $payload = [
        'ok' => true,
        'source' => 'live',
        'errors' => $normalized['errors'],
        'last_update' => date('c'),
        'status' => $status,
        'issues' => $normalized['issues'],
    ];

    // Guardamos caché ya normalizada
    @file_put_contents(
        $cacheFile,
        json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );

    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
} catch (Throwable $exception) {
    respondWithCacheOrEmpty($cacheFile, 'No se pudo contactar con Sentry: ' . $exception->getMessage());
}

/**
 * @return array{errors:int,issues:array<int,array<string,string|null>>}
 */
function normalizeIssues(array $issues, string $org, string $project): array
{
    $normalized = [];

    foreach ($issues as $issue) {
        if (!is_array($issue)) {
            continue;
        }

        $id = (string) ($issue['id'] ?? '');
        $shortId = (string) ($issue['shortId'] ?? ($issue['short_id'] ?? ''));
        $title = (string) ($issue['title'] ?? 'Sin título');
        $level = (string) ($issue['level'] ?? 'info');
        $lastSeen = (string) ($issue['lastSeen'] ?? ($issue['last_seen'] ?? ''));
        $firstSeen = (string) ($issue['firstSeen'] ?? ($issue['first_seen'] ?? ''));
        $permalink = (string) ($issue['permalink'] ?? '');

        if ($permalink === '' && $id !== '') {
            $permalink = sprintf(
                'https://sentry.io/organizations/%s/issues/%s/?project=%s',
                rawurlencode($org),
                rawurlencode($id),
                rawurlencode($project)
            );
        }

        $normalized[] = [
            'id' => $id,
            'short_id' => $shortId !== '' ? $shortId : null,
            'title' => $title,
            'level' => strtolower($level),
            'first_seen' => $firstSeen !== '' ? $firstSeen : null,
            'last_seen' => $lastSeen !== '' ? $lastSeen : null,
            'url' => $permalink !== '' ? $permalink : null,
        ];
    }

    return [
        'errors' => count($normalized),
        'issues' => $normalized,
    ];
}

function computeStatus(int $count): string
{
    if ($count === 0) {
        return 'EMPTY';
    }

    if ($count <= 5) {
        return 'OK';
    }

    return 'ERROR';
}

function callSentry(string $endpoint, string $token): string
{
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

    return $response;
}

function respondWithCacheOrEmpty(string $cacheFile, string $message): void
{
    if (is_file($cacheFile)) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cached)) {
            $cached['source'] = 'cache';
            $cached['ok'] = true;
            echo json_encode($cached, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }

    http_response_code(200);
    echo json_encode([
        'ok' => false,
        'source' => 'none',
        'errors' => 0,
        'status' => 'EMPTY',
        'issues' => [],
        'message' => $message,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function envValue(string $key): string
{
    return trim((string) (getenv($key) ?: ($_ENV[$key] ?? '')));
}
