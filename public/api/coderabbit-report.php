<?php
// public/api/coderabbit-report.php
@ini_set('max_execution_time', '650');
@ini_set('default_socket_timeout', '650');
@set_time_limit(650);

header('Access-Control-Allow-Origin: *');
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
require_once $rootPath . '/app/Services/CoderabbitClient.php';

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
    if ($value === '') {
        return null;
    }

    $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y'];
    foreach ($formats as $format) {
        $dt = DateTimeImmutable::createFromFormat('!' . $format, $value);
        if ($dt instanceof DateTimeImmutable) {
            return $dt->format('Y-m-d');
        }
    }

    $timestamp = strtotime($value);
    if ($timestamp !== false) {
        return gmdate('Y-m-d', $timestamp);
    }

    return null;
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

$client = new \App\Services\CoderabbitClient($rootPath);
$json = $client->generateReport($from, $to);

if (isset($json['status']) && is_int($json['status'])) {
    http_response_code($json['status']);
}

echo json_encode($json, JSON_UNESCAPED_UNICODE);
