<?php

declare(strict_types=1);

use App\Config\ServiceUrlProvider;

if (stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html') !== false) {
    header('Location: /accessibility');
    exit;
}

$rootPath = dirname(__DIR__, 2);
require_once $rootPath . '/vendor/autoload.php';

$container = require_once $rootPath . '/src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($method !== 'POST') {
    jsonErrorResponse(405, 'Método no permitido. Usa POST para solicitar el análisis de accesibilidad.');
}

$waveHost = 'wave.webaim.org';
$resolvedWaveHost = gethostbyname($waveHost);
if ($resolvedWaveHost === $waveHost) {
    jsonErrorResponse(503, 'No se pudo resolver ' . $waveHost . '. Verifica la conectividad o el proxy de tu entorno.');
}

if (!function_exists('curl_init')) {
    jsonErrorResponse(500, 'La extensión cURL no está activada en este servidor.');
}

$serviceConfig = $container['config']['services'] ?? [];
$serviceUrlProvider = new ServiceUrlProvider($serviceConfig);
$appBaseUrl = $serviceUrlProvider->getAppBaseUrl();
if ($appBaseUrl === '') {
    $appBaseUrl = $_ENV['APP_URL'] ?? $_ENV['APP_ORIGIN'] ?? 'http://localhost:8080';
}

$defaultRoutes = [
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

$body = json_decode((string) file_get_contents('php://input'), true);
$providedUrls = [];
if (is_array($body) && array_key_exists('urls', $body) && is_array($body['urls'])) {
    foreach ($body['urls'] as $value) {
        if (!is_string($value)) {
            continue;
        }
        $trimmed = trim($value);
        if ($trimmed === '') {
            continue;
        }
        $providedUrls[] = $trimmed;
    }
}

$urls = [];
if (!empty($providedUrls)) {
    $urls = array_values(array_unique($providedUrls));
}

if ($urls === []) {
    $urls = array_map(
        static fn (string $route): string => buildUrl($appBaseUrl, $route),
        $defaultRoutes
    );
}

$waveKey = envValue('WAVE_API_KEY');
if ($waveKey === '') {
    jsonErrorResponse(500, 'Falta configurar WAVE_API_KEY en el servidor.');
}

$results = [];
$resumen = [
    'total_errores' => 0,
    'total_contraste' => 0,
    'total_alertas' => 0,
];

$totalUrls = count($urls);
foreach ($urls as $index => $url) {
    $results[] = $pageReport = analyzePage($url, $waveKey);
    if ($pageReport['estado'] === 'exito') {
        $resumen['total_errores'] += $pageReport['errores'];
        $resumen['total_contraste'] += $pageReport['contraste'];
        $resumen['total_alertas'] += $pageReport['alertas'];
    }

    if ($index < $totalUrls - 1) {
        usleep(200_000);
    }
}

jsonResponse(200, [
    'estado' => 'exito',
    'total_paginas' => $totalUrls,
    'resumen_global' => $resumen,
    'paginas' => $results,
]);

function buildUrl(string $base, string $path): string
{
    $normalizedBase = rtrim($base, '/');
    $normalizedPath = $path === '/' ? '/' : '/' . ltrim($path, '/');
    return $normalizedBase . $normalizedPath;
}

function analyzePage(string $url, string $key): array
{
    $endpoint = 'https://wave.webaim.org/api/request';
    $payload = http_build_query([
        'key' => $key,
        'url' => $url,
        'format' => 'json',
        'reporttype' => '2',
    ]);

    $handle = curl_init($endpoint);
    if ($handle === false) {
        return [
            'estado' => 'error',
            'url' => $url,
            'mensaje' => 'No se pudo inicializar la consulta.',
        ];
    }

    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: CleanMarvel/AccessBot',
        ],
    ]);

    $response = curl_exec($handle);
    $statusCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $curlError = curl_error($handle);
    curl_close($handle);

    if ($response === false) {
        return [
            'estado' => 'error',
            'url' => $url,
            'mensaje' => 'Error de comunicación con WAVE: ' . ($curlError ?: 'sin detalles'),
        ];
    }

    if ($statusCode < 200 || $statusCode >= 300) {
        return [
            'estado' => 'error',
            'url' => $url,
            'mensaje' => 'WAVE respondió HTTP ' . $statusCode,
        ];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return [
            'estado' => 'error',
            'url' => $url,
            'mensaje' => 'Respuesta JSON inválida de WAVE.',
        ];
    }

    $statistics = $decoded['statistics'] ?? [];
    $categories = $decoded['categories'] ?? [];

    return [
        'estado' => 'exito',
        'url' => $url,
        'titulo' => (string) ($statistics['pagetitle'] ?? ''),
        'errores' => (int) ($categories['error']['count'] ?? 0),
        'contraste' => (int) ($categories['contrast']['count'] ?? 0),
        'alertas' => (int) ($categories['alert']['count'] ?? 0),
        'aimScore' => isset($statistics['AIMscore']) && is_numeric($statistics['AIMscore'])
            ? (float) $statistics['AIMscore']
            : null,
        'waveUrl' => isset($statistics['waveurl']) && $statistics['waveurl'] !== '' ? (string) $statistics['waveurl'] : null,
    ];
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

function envValue(string $key): string
{
    return trim((string) (getenv($key) ?: ($_ENV[$key] ?? '')));
}
