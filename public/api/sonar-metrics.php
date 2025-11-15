<?php

declare(strict_types=1);

use DateTimeImmutable;
use DateTimeInterface;
use Dotenv\Dotenv;
use Throwable;

$rootPath = dirname(__DIR__, 2);
$vendorAutoload = $rootPath . '/vendor/autoload.php';

// 1) Autoload de Composer (si existe)
if (is_file($vendorAutoload)) {
    require_once $vendorAutoload;
}

// 2) Cargar .env
if (class_exists(Dotenv::class)) {
    // Si tienes vlucas/phpdotenv instalado, lo usamos normal
    Dotenv::createImmutable($rootPath)->safeLoad();
} else {
    // Fallback manual por si NO está instalado Dotenv,
    // pero existe un archivo .env en la raíz del proyecto.
    $envFile = $rootPath . '/.env';
    if (is_file($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            foreach ($lines as $line) {
                $line = trim($line);
                // Ignorar comentarios
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                // Solo líneas con "="
                if (!str_contains($line, '=')) {
                    continue;
                }

                [$name, $value] = explode('=', $line, 2);
                $name  = trim($name);
                $value = trim($value);

                // Quitar comillas si las hay
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

// Respuestas siempre en JSON sin cache
header('Content-Type: application/json');
header('Cache-Control: no-store, max-age=0');

// Solo permitimos GET
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    jsonErrorResponse(405, 'Método no permitido. Usa GET para consultar el panel de calidad.');
}

// Leemos configuración desde entorno
$token = trim((string) (getenv('SONARCLOUD_TOKEN') ?: ($_ENV['SONARCLOUD_TOKEN'] ?? '')));
$projectKey = trim((string) (getenv('SONARCLOUD_PROJECT_KEY') ?: ($_ENV['SONARCLOUD_PROJECT_KEY'] ?? '')));

// ⚠️ IMPORTANTE: en tu .env debe estar SONARCLOUD_PROJECT_KEY=20Luisma_marvel
if ($token === '') {
    jsonErrorResponse(500, 'Configura SONARCLOUD_TOKEN en tu entorno para consultar SonarCloud.');
}

if ($projectKey === '') {
    jsonErrorResponse(500, 'Configura SONARCLOUD_PROJECT_KEY en tu entorno para consultar SonarCloud.');
}

// -----------------------------------------------------------------------------
// Configuración de métricas a pedir a SonarCloud
// -----------------------------------------------------------------------------
$metrics = [
    'ncloc' => ['sonar_key' => 'ncloc', 'cast' => 'int'],
    'code_smells' => ['sonar_key' => 'code_smells', 'cast' => 'int'],
    'bugs' => ['sonar_key' => 'bugs', 'cast' => 'int'],
    'vulnerabilities' => ['sonar_key' => 'vulnerabilities', 'cast' => 'int'],
    'coverage' => ['sonar_key' => 'coverage', 'cast' => 'float'],
    'duplicated_code' => ['sonar_key' => 'duplicated_lines_density', 'cast' => 'float'],
    'complexity' => ['sonar_key' => 'complexity', 'cast' => 'int'],
    'sqale_rating' => ['sonar_key' => 'sqale_rating', 'cast' => 'string'],
];

$sonarMetricKeys = array_values(array_unique(array_map(
    static fn(array $config): string => $config['sonar_key'],
    $metrics
)));

$endpoint = sprintf(
    'https://sonarcloud.io/api/measures/component?component=%s&metricKeys=%s',
    rawurlencode($projectKey),
    rawurlencode(implode(',', $sonarMetricKeys))
);

$handle = curl_init($endpoint);

if ($handle === false) {
    jsonErrorResponse(502, 'No se pudo iniciar la solicitud hacia SonarCloud.');
}

// Configuración de cURL usando autenticación Bearer (token de usuario)
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
    jsonErrorResponse(502, 'No se pudo contactar SonarCloud: ' . ($curlError ?: 'Error desconocido.'));
}

if ($statusCode < 200 || $statusCode >= 300) {
    jsonErrorResponse(
        $statusCode ?: 502,
        'SonarCloud devolvió un error inesperado al consultar las métricas (HTTP ' . $statusCode . ').'
    );
}

// Parseamos respuesta
$payload = json_decode($response, true);
if (!is_array($payload) || !isset($payload['component'])) {
    jsonErrorResponse(502, 'La respuesta de SonarCloud no tiene el formato esperado.');
}

$component = $payload['component'];
$measures = [];
foreach (($component['measures'] ?? []) as $measure) {
    if (!is_array($measure) || empty($measure['metric'])) {
        continue;
    }
    $measures[(string) $measure['metric']] = $measure;
}

// Normalizamos las métricas a nuestro formato (compatibles con tu front)
$metricsOutput = [];
foreach ($metrics as $metricKey => $config) {
    $sourceKey = $config['sonar_key'];
    $cast = $config['cast'];
    $metricValue = $measures[$sourceKey]['value'] ?? null;
    $metricsOutput[$metricKey] = [
        'key' => $metricKey,
        'value' => normalizeMetricValue($metricValue, $cast),
    ];
}

// Payload final para el frontend
$result = [
    'project_key' => (string) ($component['key'] ?? $projectKey),
    'project_name' => (string) ($component['name'] ?? $projectKey),
    'updated_at' => normalizeDateTime($component['analysisDate'] ?? null),
    'metrics' => $metricsOutput,
];

// Calculamos un score de calidad propio (0–100)
$result['quality_score'] = computeQualityScore($metricsOutput);

// Respondemos
jsonResponse(200, $result);

/**
 * @param array<string, array{key:string,value:int|float|string|null}> $metrics
 */
function computeQualityScore(array $metrics): int
{
    $score = 100;

    $codeSmells = (int) round((float) ($metrics['code_smells']['value'] ?? 0));
    $bugs = (int) round((float) ($metrics['bugs']['value'] ?? 0));
    $vulnerabilities = (int) round((float) ($metrics['vulnerabilities']['value'] ?? 0));
    $duplication = (float) ($metrics['duplicated_code']['value'] ?? 0.0);
    $coverage = (float) ($metrics['coverage']['value'] ?? 0.0);

    // Penalizaciones
    $score -= min(30, (int) ceil($codeSmells / 25));
    $score -= min(20, (int) ceil($bugs * 1.5));
    $score -= min(15, (int) ceil($vulnerabilities * 2));
    $score -= min(15, (int) round($duplication / 2));

    if ($coverage < 80) {
        $score -= (int) min(10, ceil((80 - $coverage) / 5));
    }

    $rating = (string) ($metrics['sqale_rating']['value'] ?? 'A');
    $ratingPenalty = [
        'A' => 0,
        'B' => 4,
        'C' => 8,
        'D' => 12,
        'E' => 20,
    ];
    $score -= $ratingPenalty[$rating] ?? 5;

    return max(0, min(100, $score));
}

function normalizeMetricValue(mixed $value, string $cast): int|float|string|null
{
    $normalized = null;

    if ($value !== null && $value !== '') {
        $normalized = $cast === 'string'
            ? normalizeMetricString($value)
            : normalizeMetricNumber($value, $cast);
    }

    return $normalized;
}

function normalizeDateTime(mixed $rawValue): string
{
    $candidate = extractNormalizedDateTime($rawValue);

    return $candidate ?? gmdate(DateTimeInterface::ATOM);
}

function jsonResponse(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    header('Cache-Control: no-store, max-age=0');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function jsonErrorResponse(int $status, string $message): void
{
    jsonResponse($status, ['error' => $message]);
}

function normalizeMetricString(mixed $value): string
{
    return strtoupper((string) $value);
}

function normalizeMetricNumber(mixed $value, string $cast): int|float|null
{
    if (!is_numeric($value)) {
        return null;
    }

    $numeric = (float) $value;

    return $cast === 'float'
        ? round($numeric, 2)
        : (int) round($numeric);
}

function extractNormalizedDateTime(mixed $rawValue): ?string
{
    if (!is_string($rawValue)) {
        return null;
    }

    $trimmed = trim($rawValue);
    if ($trimmed === '') {
        return null;
    }

    try {
        $date = new DateTimeImmutable($trimmed);
        return $date->format(DateTimeInterface::ATOM);
    } catch (Throwable) {
        $timestamp = strtotime($trimmed);
        if ($timestamp !== false) {
            return gmdate(DateTimeInterface::ATOM, $timestamp);
        }
    }

    return null;
}
