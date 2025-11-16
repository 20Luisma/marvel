<?php
declare(strict_types=1);
// public/api/github-activity.php
@ini_set('max_execution_time', '650');
@ini_set('default_socket_timeout', '650');
@set_time_limit(650);

use App\Services\GithubClient;
use DateTimeImmutable;

$allowedOrigin = trim((string) ($_ENV['APP_ORIGIN'] ?? $_ENV['APP_URL'] ?? ''));
$originHeader = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
if ($allowedOrigin !== '' && $originHeader !== '') {
    if ($originHeader === $allowedOrigin) {
        header('Access-Control-Allow-Origin: ' . $allowedOrigin);
        header('Vary: Origin');
    } else {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Origen no permitido'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($requestMethod === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['status' => 'ok'], JSON_UNESCAPED_UNICODE);
    exit;
}

$rootPath = dirname(__DIR__, 2);
$autoload = $rootPath . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

/**
 * Normaliza una fecha proveniente de la query string a YYYY-MM-DD.
 */
function normalizeDateParam(string $param, string $default): string
{
    $raw = $_GET[$param] ?? null;
    if ($raw === null || trim((string) $raw) === '') {
        return $default;
    }

    $normalized = normalizeDate((string) $raw);
    if ($normalized === null) {
        respondInvalidDate($param, (string) $raw);
    }

    return $normalized;
}

/**
 * @return string|null Devuelve YYYY-MM-DD o null si es inválida.
 */
function normalizeDate(string $value): ?string
{
    $value = trim($value);
    $normalized = null;

    if ($value !== '') {
        $normalized = normalizeDateFromFormats($value);
        if ($normalized === null) {
            $normalized = normalizeDateFromTimestamp($value);
        }
    }

    return $normalized;
}

function respondInvalidDate(string $param, string $value): void
{
    http_response_code(400);
    echo json_encode([
        'error' => "Fecha '{$param}' inválida. Usa YYYY-MM-DD o DD/MM/AAAA.",
        'invalid_value' => $value,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$from = normalizeDateParam('from', date('Y-m-d', strtotime('-14 days')));
$to   = normalizeDateParam('to', date('Y-m-d'));

$client = new GithubClient($rootPath);
$json = $client->fetchActivity($from, $to);

if (isset($json['status']) && is_int($json['status'])) {
    http_response_code($json['status']);
}

echo json_encode($json, JSON_UNESCAPED_UNICODE);

function normalizeDateFromFormats(string $value): ?string
{
    $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y'];
    foreach ($formats as $format) {
        $dt = DateTimeImmutable::createFromFormat('!' . $format, $value);
        if ($dt instanceof DateTimeImmutable) {
            return $dt->format('Y-m-d');
        }
    }

    return null;
}

function normalizeDateFromTimestamp(string $value): ?string
{
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return null;
    }

    return gmdate('Y-m-d', $timestamp);
}
