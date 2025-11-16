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
    // Llamada a Sentry: pedimos eventos (no issues) para listar cada aparición, aunque pertenezca al mismo issue.
    'https://sentry.io/api/0/projects/%s/%s/events/?statsPeriod=24h&per_page=20&expand=issue',
    rawurlencode($org),
    rawurlencode($project)
);

try {
    $response = callSentry($endpoint, $token);
    $events = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

    if (!is_array($events)) {
        throw new RuntimeException('La respuesta de Sentry no tiene el formato esperado.');
    }

    $normalized = normalizeEvents($events, $org, $project);
    $status = computeStatus($normalized['errors']);

    $payload = [
        'ok' => true,
        'source' => 'live',
        'errors' => $normalized['errors'],
        'last_update' => date('c'),
        'status' => $status,
        'events' => $normalized['events'],
        // Alias legacy para el JS antiguo mientras migramos la UI
        'issues' => $normalized['events'],
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
 * Normaliza los eventos recientes para mostrarlos como tarjetas repetidas cuando ocurre el mismo issue.
 *
 * @return array{errors:int,events:array<int,array<string,string|null>>}
 */
function normalizeEvents(array $events, string $org, string $project): array
{
    $normalized = [];

    foreach ($events as $event) {
        if (!is_array($event)) {
            continue;
        }

        $eventId = (string) ($event['eventID'] ?? ($event['id'] ?? ''));
        $groupId = (string) ($event['groupID'] ?? ($event['issue']['id'] ?? ''));
        $shortId = (string) ($event['issue']['shortId'] ?? ($event['issue']['short_id'] ?? ''));
        $title = (string) ($event['title'] ?? ($event['message'] ?? 'Evento de Sentry'));
        $level = (string) ($event['level'] ?? '');
        $datetime = (string) ($event['dateCreated'] ?? ($event['timestamp'] ?? ($event['received'] ?? '')));

        if ($level === '' && isset($event['tags']) && is_array($event['tags'])) {
            foreach ($event['tags'] as $tag) {
                if (($tag['key'] ?? '') === 'level' && isset($tag['value'])) {
                    $level = (string) $tag['value'];
                    break;
                }
            }
        }

        $permalink = buildEventUrl($org, $project, $groupId, $eventId);

        $normalized[] = [
            'id' => $eventId,
            'issue_id' => $groupId !== '' ? $groupId : null,
            'short_id' => $shortId !== '' ? $shortId : null,
            'title' => $title,
            'level' => strtolower($level !== '' ? $level : 'info'),
            'last_seen' => $datetime !== '' ? $datetime : null,
            'url' => $permalink !== '' ? $permalink : null,
        ];
    }

    return [
        'errors' => count($normalized),
        'events' => $normalized,
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
    $attempts = 0;
    $lastError = null;

    while ($attempts < 3) {
        $handle = curl_init($endpoint);
        if ($handle === false) {
            $lastError = 'No se pudo iniciar la solicitud a Sentry.';
            $attempts++;
            continue;
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

        if ($response !== false && $statusCode >= 200 && $statusCode < 300) {
            return $response;
        }

        $lastError = $response === false
            ? ($curlError ?: 'Error desconocido.')
            : 'HTTP ' . $statusCode;

        $attempts++;
        usleep(200_000); // pequeño backoff
    }

    throw new RuntimeException('No se pudo contactar Sentry: ' . ($lastError ?? 'Error desconocido.'));
}

function buildEventUrl(string $org, string $project, string $groupId, string $eventId): string
{
    if ($groupId !== '' && $eventId !== '') {
        return sprintf(
            'https://sentry.io/organizations/%s/issues/%s/events/%s/?project=%s',
            rawurlencode($org),
            rawurlencode($groupId),
            rawurlencode($eventId),
            rawurlencode($project)
        );
    }

    if ($groupId !== '') {
        return sprintf(
            'https://sentry.io/organizations/%s/issues/%s/?project=%s',
            rawurlencode($org),
            rawurlencode($groupId),
            rawurlencode($project)
        );
    }

    return '';
}

function respondWithCacheOrEmpty(string $cacheFile, string $message): void
{
    if (is_file($cacheFile)) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cached)) {
            if (!isset($cached['events']) && isset($cached['issues']) && is_array($cached['issues'])) {
                $cached['events'] = $cached['issues'];
            }
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
        'events' => [],
        'issues' => [],
        'message' => $message,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function envValue(string $key): string
{
    return trim((string) (getenv($key) ?: ($_ENV[$key] ?? '')));
}
