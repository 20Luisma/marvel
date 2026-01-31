<?php

declare(strict_types=1);

use DateTimeImmutable;
use DateTimeInterface;
use Dotenv\Dotenv;
use Throwable;

if (stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html') !== false) {
    header('Location: /sonar');
    exit;
}

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

// Leemos configuración desde entorno (probamos todas las fuentes posibles)
$token = trim((string) (getenv('SONARCLOUD_TOKEN') ?: ($_ENV['SONARCLOUD_TOKEN'] ?? ($_SERVER['SONARCLOUD_TOKEN'] ?? ''))));
$projectKey = trim((string) (getenv('SONARCLOUD_PROJECT_KEY') ?: ($_ENV['SONARCLOUD_PROJECT_KEY'] ?? ($_SERVER['SONARCLOUD_PROJECT_KEY'] ?? ''))));

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

$response = sonarRequestWithRetry($endpoint, $token, 2);

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

// Calculamos un score de calidad propio (0–100) un poco más suave
$result['quality_score'] = computeQualityScore($metricsOutput);

// Respondemos
jsonResponse(200, $result);

/**
 * Calcula un score de calidad 0–100 inspirado en Sonar, pero más suave.
 *
 * Premia fuerte tener 0 bugs y 0 vulnerabilidades,
 * penaliza poco por code smells y duplicación moderada,
 * y solo penaliza cobertura si Sonar tiene un valor real.
 *
 * @param array<string, array{key:string,value:int|float|string|null}> $metrics
 */
function computeQualityScore(array $metrics): int
{
    $score = 100;

    $codeSmells = (int) round((float) ($metrics['code_smells']['value'] ?? 0));
    $bugs = (int) round((float) ($metrics['bugs']['value'] ?? 0));
    $vulnerabilities = (int) round((float) ($metrics['vulnerabilities']['value'] ?? 0));
    $duplication = (float) ($metrics['duplicated_code']['value'] ?? 0.0);

    // Coverage: respetamos null para saber si hay dato real o no
    $coverageRaw = $metrics['coverage']['value'] ?? null;
    $coverage = is_numeric($coverageRaw) ? (float) $coverageRaw : null;

    // 1) Code smells: penalización suave (1 punto cada 40, máx 20)
    if ($codeSmells > 0) {
        $score -= min(20, (int) ceil($codeSmells / 40));
    }

    // 2) Bugs: seguimos siendo duros
    if ($bugs > 0) {
        $score -= min(20, (int) ceil($bugs * 1.5));
    }

    // 3) Vulnerabilidades: muy duros
    if ($vulnerabilities > 0) {
        $score -= min(15, (int) ceil($vulnerabilities * 2));
    }

    // 4) Duplicación: cada 2% de duplicación resta 1 punto, máx 15
    if ($duplication > 0) {
        $score -= min(15, (int) round($duplication / 2));
    }

    // 5) Cobertura: solo penalizamos si hay dato real
    if ($coverage !== null && $coverage < 80.0) {
        // Cada 10% por debajo de 80 quita 1 punto, máx 8
        $score -= (int) min(8, ceil((80 - $coverage) / 10));
    }

    // 6) Rating de mantenibilidad (sqale_rating)
    $rating = (string) ($metrics['sqale_rating']['value'] ?? 'A');
    $ratingPenalty = [
        'A' => 0,
        'B' => 3,
        'C' => 6,
        'D' => 10,
        'E' => 15,
    ];
    $score -= $ratingPenalty[$rating] ?? 5;

    // BONUS: si no hay bugs ni vulnerabilidades, premio extra
    if ($bugs === 0 && $vulnerabilities === 0) {
        $score += 5;
    }

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

/**
 * Ejecuta la llamada a SonarCloud con un pequeño reintento si falla.
 */
function sonarRequestWithRetry(string $endpoint, string $token, int $maxAttempts = 2): string
{
    $attempt = 0;
    $lastError = null;

    while ($attempt < $maxAttempts) {
        $handle = curl_init($endpoint);

        if ($handle === false) {
            $lastError = 'No se pudo iniciar la solicitud hacia SonarCloud.';
            $attempt++;
            usleep(150_000);
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
            ? 'No se pudo contactar SonarCloud: ' . ($curlError ?: 'Error desconocido.')
            : 'SonarCloud devolvió un error inesperado (HTTP ' . $statusCode . ').';

        $attempt++;
        usleep(150_000); // backoff breve antes de reintentar
    }

    jsonErrorResponse(502, $lastError ?? 'No se pudo contactar SonarCloud.');
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
