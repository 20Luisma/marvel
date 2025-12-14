<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Creawebes\Rag\Observability\PrometheusMetrics;

$traceId = resolve_trace_id();
header('X-Trace-Id: ' . $traceId);

$container = require_once __DIR__ . '/../src/bootstrap.php';
$requestStart = microtime(true);
$rawInput = read_raw_body();
$_SERVER['__RAW_INPUT__'] = $rawInput;

/**
 * @return array<int, string>
 */
function resolve_allowed_origins(): array
{
    $configured = $_ENV['ALLOWED_ORIGINS'] ?? getenv('ALLOWED_ORIGINS') ?: null;
    if (is_string($configured) && trim($configured) !== '') {
        $entries = array_filter(array_map('trim', explode(',', $configured)));
        if ($entries !== []) {
            return array_values($entries);
        }
    }

    return [
        'http://localhost:8080',
        'https://iamasterbigschool.contenido.creawebes.com',
        'https://rag-service.contenido.creawebes.com',
    ];
}

$allowedOrigins = resolve_allowed_origins();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
$origin = is_string($requestOrigin) ? trim($requestOrigin) : '';

PrometheusMetrics::incrementRequests();

header('Vary: Origin');

if ($origin !== '' && !in_array($origin, $allowedOrigins, true)) {
    if ($method === 'OPTIONS') {
        http_response_code(403);
    } else {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Origin not allowed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    log_request_event($path, http_response_code(), $requestStart, 'origin-not-allowed');
    return;
}

$allowedOrigin = $origin !== '' ? $origin : ($allowedOrigins[0] ?? '');
if ($allowedOrigin !== '') {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Internal-Signature, X-Internal-Timestamp, X-Internal-Caller, X-Trace-Id');
header('Access-Control-Max-Age: 86400');

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($method === 'GET' && $path === '/metrics') {
    PrometheusMetrics::respond('rag-service');
    log_request_event($path, 200, $requestStart);
    return;
}

if ($path === '/metrics') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    log_request_event($path, 405, $requestStart, 'method-not-allowed');
    return;
}

if ($method === 'GET' && $path === '/health') {
    $embeddingsEnabled = filter_var(
        $_ENV['RAG_USE_EMBEDDINGS'] ?? getenv('RAG_USE_EMBEDDINGS'),
        FILTER_VALIDATE_BOOL
    ) === true;

    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        [
            'status' => 'ok',
            'service' => 'rag-service',
            'time' => date('c'),
            'embeddings_enabled' => $embeddingsEnabled,
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    log_request_event($path, 200, $requestStart);
    return;
}

if ($method !== 'OPTIONS') {
    $authResult = authorize_internal_request($method, $path, $rawInput);
    if ($authResult['ok'] === false) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Unauthorized request'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        log_request_event($path, 401, $requestStart, $authResult['reason'] ?? 'signature');
        return;
    }
}

if ($method === 'POST' && $path === '/rag/heroes') {
    $controller = $container['ragController'] ?? null;
    if ($controller instanceof \Creawebes\Rag\Controllers\RagController) {
        $controller->compareHeroes();
        $status = http_response_code() ?: 200;
        if ($status >= 500) {
            PrometheusMetrics::incrementErrors();
        }
        log_request_event($path, $status, $requestStart);
        return;
    }

    PrometheusMetrics::incrementErrors();
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Controlador RAG no disponible.'], JSON_UNESCAPED_UNICODE);
    log_request_event($path, 500, $requestStart, 'controller-missing');
    return;
}

if ($method === 'POST' && $path === '/rag/heroes/upsert') {
    $controller = $container['ragController'] ?? null;
    if ($controller instanceof \Creawebes\Rag\Controllers\RagController) {
        $controller->upsertHero();
        $status = http_response_code() ?: 200;
        if ($status >= 500) {
            PrometheusMetrics::incrementErrors();
        }
        log_request_event($path, $status, $requestStart);
        return;
    }

    PrometheusMetrics::incrementErrors();
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Controlador RAG no disponible.'], JSON_UNESCAPED_UNICODE);
    log_request_event($path, 500, $requestStart, 'controller-missing');
    return;
}

if ($method === 'POST' && $path === '/rag/agent') {
    $useCase = $container['askMarvelAgentUseCase'] ?? null;
    if ($useCase instanceof \Creawebes\Rag\Application\UseCase\AskMarvelAgentUseCase) {
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Payload inválido.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            log_request_event($path, 400, $requestStart, 'invalid-json');
            return;
        }

        $question = isset($data['question']) ? trim((string) $data['question']) : '';
        if ($question === '') {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'La pregunta es obligatoria.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            log_request_event($path, 400, $requestStart, 'empty-question');
            return;
        }

        try {
            $response = $useCase->ask($question);
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            log_request_event($path, 200, $requestStart);
            return;
        } catch (\Throwable $exception) {
            PrometheusMetrics::incrementErrors();
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Error interno llamando al Marvel Agent.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            log_request_event($path, 500, $requestStart, 'agent-error');
            return;
        }
    }

    PrometheusMetrics::incrementErrors();
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Caso de uso Marvel Agent no disponible.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    log_request_event($path, 500, $requestStart, 'agent-usecase-missing');
    return;
}

http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['error' => 'Ruta no encontrada.'], JSON_UNESCAPED_UNICODE);
log_request_event($path, 404, $requestStart, 'not-found');

/**
 * @return array{ok: bool, reason?: string}
 */
function authorize_internal_request(string $method, string $path, string $rawBody): array
{
    $sharedKey = $_ENV['INTERNAL_API_KEY'] ?? getenv('INTERNAL_API_KEY') ?: '';
    $normalizedKey = is_string($sharedKey) ? trim($sharedKey) : '';
    if ($normalizedKey === '') {
        return ['ok' => true, 'reason' => 'signature-disabled'];
    }

    $signature = $_SERVER['HTTP_X_INTERNAL_SIGNATURE'] ?? '';
    $timestampHeader = $_SERVER['HTTP_X_INTERNAL_TIMESTAMP'] ?? '';
    $caller = $_SERVER['HTTP_X_INTERNAL_CALLER'] ?? '';
    $timestamp = is_numeric($timestampHeader) ? (int) $timestampHeader : 0;

    if (!is_string($signature) || trim($signature) === '' || $timestamp <= 0) {
        return ['ok' => false, 'reason' => 'missing-signature'];
    }

    $canonical = strtoupper($method) . "\n" . $path . "\n" . $timestamp . "\n" . hash('sha256', $rawBody);
    $expected = hash_hmac('sha256', $canonical, $normalizedKey);

    if (!hash_equals($expected, trim((string) $signature))) {
        return ['ok' => false, 'reason' => 'signature-mismatch'];
    }

    if (abs(time() - $timestamp) > 300) {
        return ['ok' => false, 'reason' => 'timestamp-out-of-range'];
    }

    $allowedCallers = resolve_allowed_callers();
    $normalizedCaller = normalize_host((string) $caller);
    if ($allowedCallers !== [] && $normalizedCaller !== '' && !in_array($normalizedCaller, $allowedCallers, true)) {
        return ['ok' => false, 'reason' => 'caller-not-allowed'];
    }

    return ['ok' => true];
}

/**
 * @return array<int, string>
 */
function resolve_allowed_callers(): array
{
    $configured = $_ENV['ALLOWED_INTERNAL_CALLERS'] ?? getenv('ALLOWED_INTERNAL_CALLERS') ?: null;
    if (is_string($configured) && trim($configured) !== '') {
        $entries = array_filter(array_map('normalize_host', explode(',', $configured)));
        if ($entries !== []) {
            return array_values($entries);
        }
    }

    // Derive from allowed origins as fallback (app host first).
    return array_values(array_filter(array_map(static function (string $origin): string {
        return normalize_host(parse_url($origin, PHP_URL_HOST) ?: $origin);
    }, resolve_allowed_origins())));
}

function normalize_host(?string $host): string
{
    $value = strtolower(trim((string) $host));
    if ($value === '') {
        return '';
    }

    if (str_contains($value, '://')) {
        $parsed = parse_url($value, PHP_URL_HOST);
        if (is_string($parsed) && $parsed !== '') {
            $value = $parsed;
        }
    }

    return explode('/', $value)[0];
}

function log_request_event(string $path, int $statusCode, float $startTime, ?string $error = null): void
{
    $logFile = __DIR__ . '/../storage/logs/requests.log';
    $directory = dirname($logFile);

    $entry = [
        'timestamp' => date('c'),
        'trace_id' => $_SERVER['__TRACE_ID__'] ?? ($_SERVER['HTTP_X_TRACE_ID'] ?? null),
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        'path' => $path,
        'status' => $statusCode,
        'duration_ms' => (int) round((microtime(true) - $startTime) * 1000),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'caller' => $_SERVER['HTTP_X_INTERNAL_CALLER'] ?? null,
    ];

    if ($error !== null) {
        $entry['error'] = $error;
    }

    $encoded = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($encoded === false) {
        return;
    }

    try {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        if (!is_dir($directory)) {
            if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException('Cannot create dir: ' . $directory);
            }
        }

        $bytes = file_put_contents($logFile, $encoded . PHP_EOL, FILE_APPEND | LOCK_EX);
        if ($bytes === false) {
            throw new RuntimeException('Cannot write log file: ' . $logFile);
        }
    } catch (Throwable $e) {
        error_log('[rag-service][log_write_failed] ' . $e->getMessage());
        error_log('[rag-service][log_payload] ' . $encoded);
    } finally {
        restore_error_handler();
    }
}

function resolve_trace_id(): string
{
    $existing = $_SERVER['__TRACE_ID__'] ?? null;
    if (is_string($existing) && $existing !== '') {
        return $existing;
    }

    $header = $_SERVER['HTTP_X_TRACE_ID'] ?? null;
    if (is_string($header)) {
        $candidate = trim($header);
        if ($candidate !== '' && preg_match('/^[A-Za-z0-9._-]{1,128}$/', $candidate) === 1) {
            $_SERVER['__TRACE_ID__'] = $candidate;
            return $candidate;
        }
    }

    try {
        $generated = bin2hex(random_bytes(16));
    } catch (\Throwable) {
        $generated = str_replace('.', '', uniqid('trace_', true));
    }

    $_SERVER['__TRACE_ID__'] = $generated;

    return $generated;
}

function read_raw_body(): string
{
    $content = file_get_contents('php://input');
    if ($content === false) {
        return '';
    }

    return (string) $content;
}
