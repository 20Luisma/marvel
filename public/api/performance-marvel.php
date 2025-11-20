<?php

declare(strict_types=1);

use App\Config\ServiceUrlProvider;
use Dotenv\Dotenv;

set_time_limit(120);

if (stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html') !== false) {
    header('Location: /performance');
    exit;
}

$rootPath = dirname(__DIR__, 2);
require_once $rootPath . '/vendor/autoload.php';

if (class_exists(Dotenv::class)) {
    Dotenv::createImmutable($rootPath)->safeLoad();
} else {
    $envFile = $rootPath . '/.env';
    if (is_file($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2) + [1 => '']);
            if ($key !== '' && getenv($key) === false) {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
            }
        }
    }
}

$serviceConfigPath = $rootPath . '/config/services.php';
$serviceConfig = is_file($serviceConfigPath) ? require_once $serviceConfigPath : ['environments' => []];
$serviceUrlProvider = new ServiceUrlProvider($serviceConfig);
$appBaseUrl = $serviceUrlProvider->getAppBaseUrl();

$apiKey = envValue('PAGESPEED_API_KEY');
if ($apiKey === '') {
    jsonErrorResponse(500, 'Falta configurar PAGESPEED_API_KEY en el servidor.');
}

$paths = [
    '/',
    '/albums',
    '/heroes',
    '/movies',
    '/comic',
    '/panel-github',
    '/sonar',
    '/sentry',
    '/seccion',
    '/oficial-marvel',
    '/readme',
];

$publicBaseUrl = trim((string) ($_ENV['APP_PUBLIC_URL'] ?? 'https://iamasterbigschool.contenido.creawebes.com'));
$urls = array_map(
    static fn (string $path): string => rtrim($publicBaseUrl, '/') . $path,
    $paths
);

$results = fetchPerformanceReports($urls, $apiKey);
$total = count($results);

jsonResponse(200, [
    'estado' => 'exito',
    'total_paginas' => $total,
    'paginas' => $results,
]);

function fetchPerformanceReports(array $urls, string $apiKey): array
{
    $multiHandle = curl_multi_init();
    $handles = [];

    foreach ($urls as $url) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => sprintf(
                'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=%s&strategy=mobile&key=%s',
                rawurlencode($url),
                rawurlencode($apiKey)
            ),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        curl_multi_add_handle($multiHandle, $ch);
        $handles[(int) $ch] = ['handle' => $ch, 'url' => $url];
    }

    $running = null;
    do {
        $status = curl_multi_exec($multiHandle, $running);
        if ($status !== CURLM_OK) {
            break;
        }
        if ($running) {
            if (curl_multi_select($multiHandle) === -1) {
                usleep(100_000);
            }
        }
    } while ($running);

    $results = [];
    foreach ($handles as $info) {
        /** @var resource $handle */
        $handle = $info['handle'];
        $url = $info['url'];
        $errNo = curl_errno($handle);
        $response = curl_multi_getcontent($handle);
        $statusCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $error = curl_error($handle);
        curl_multi_remove_handle($multiHandle, $handle);
        curl_close($handle);

        if ($errNo === CURLE_OPERATION_TIMEDOUT) {
            $results[] = [
                'url' => $url,
                'estado' => 'error',
                'mensaje' => 'timeout',
            ];
            continue;
        }

        if ($response === false) {
            $results[] = [
                'url' => $url,
                'estado' => 'error',
                'mensaje' => 'Error de comunicación con PageSpeed Insights: ' . ($error ?: 'sin detalle'),
            ];
            continue;
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            $payload = json_decode($response, true);
            $message = is_array($payload) && isset($payload['error']['message'])
                ? (string) $payload['error']['message']
                : 'PageSpeed Insights respondió HTTP ' . $statusCode;
            $results[] = [
                'url' => $url,
                'estado' => 'error',
                'mensaje' => $message,
            ];
            continue;
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            $results[] = [
                'url' => $url,
                'estado' => 'error',
                'mensaje' => 'Respuesta JSON inválida de PageSpeed Insights.',
            ];
            continue;
        }

        $results[] = parsePerformanceReport($url, $decoded);
    }

    curl_multi_close($multiHandle);
    return $results;
}

function parsePerformanceReport(string $url, array $payload): array
{
    $result = $payload['lighthouseResult'] ?? [];
    $categories = $result['categories'] ?? [];
    $audits = $result['audits'] ?? [];
    $performance = $categories['performance'] ?? [];
    $performanceScore = isset($performance['score']) ? (float) $performance['score'] * 100.0 : null;

    return [
        'url' => $url,
        'estado' => 'exito',
        'performance' => [
            'score' => $performanceScore !== null ? (int) round($performanceScore) : null,
            'lcp' => formatMetric($audits, 'largest-contentful-paint'),
            'fcp' => formatMetric($audits, 'first-contentful-paint'),
            'cls' => formatMetric($audits, 'cumulative-layout-shift'),
            'tbt' => formatMetric($audits, 'total-blocking-time', 'interaction-to-next-paint'),
        ],
        'oportunidades' => extractOpportunities($audits),
    ];
}

function formatMetric(array $audits, string $primary, ?string $fallback = null): ?string
{
    $attempts = [$primary];
    if ($fallback !== null) {
        $attempts[] = $fallback;
    }

    foreach ($attempts as $key) {
        if (!isset($audits[$key]) || !is_array($audits[$key])) {
            continue;
        }

        $audit = $audits[$key];
        if (!empty($audit['displayValue'])) {
            return (string) $audit['displayValue'];
        }

        if (isset($audit['numericValue']) && is_numeric($audit['numericValue'])) {
            $value = (float) $audit['numericValue'];
            if ($value >= 1000) {
                return round($value / 1000, 2) . ' s';
            }
            return round($value, 1) . ' ms';
        }
    }

    return null;
}

function extractOpportunities(array $audits): array
{
    $items = [];
    foreach ($audits as $id => $audit) {
        if (!is_array($audit)) {
            continue;
        }

        $details = $audit['details'] ?? [];
        $type = $details['type'] ?? null;
        if ($type !== 'opportunity') {
            continue;
        }

        $savings = '';
        if (isset($details['overallSavingsMs']) && is_numeric($details['overallSavingsMs'])) {
            $savings = round((float) $details['overallSavingsMs']) . ' ms';
        } elseif (isset($details['overallSavingsBytes']) && is_numeric($details['overallSavingsBytes'])) {
            $savings = round((float) $details['overallSavingsBytes'] / 1024, 1) . ' KiB';
        }

        $items[] = [
            'id' => (string) $id,
            'titulo' => (string) ($audit['title'] ?? $id),
            'descripcion' => (string) ($audit['description'] ?? $audit['displayValue'] ?? ''),
            'ahorro' => $savings !== '' ? $savings : ($audit['displayValue'] ?? ''),
        ];
    }

    return array_slice($items, 0, 6);
}

function envValue(string $key): string
{
    return trim((string) (getenv($key) ?: ($_ENV[$key] ?? '')));
}

function jsonResponse(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonErrorResponse(int $status, string $message): void
{
    http_response_code($status);
    echo json_encode([
        'estado' => 'error',
        'mensaje' => $message,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
