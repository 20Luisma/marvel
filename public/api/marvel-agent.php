<?php

declare(strict_types=1);

use App\Shared\Infrastructure\Http\CurlHttpClient;
use App\Shared\Infrastructure\Security\InternalRequestSigner;
use App\Security\Sanitizer;
use App\Security\Validation\JsonValidator;

$rootPath = dirname(__DIR__, 2);
require_once $rootPath . '/vendor/autoload.php';

// Cargar .env si está disponible (importante para staging/hosting)
if (class_exists(\Dotenv\Dotenv::class)) {
    \Dotenv\Dotenv::createImmutable($rootPath)->safeLoad();
} else {
    // Fallback manual por si NO está instalado Dotenv
    $envFile = $rootPath . '/.env';
    if (is_file($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) continue;
                if (!str_contains($line, '=')) continue;
                [$name, $value] = explode('=', $line, 2);
                $name = trim($name);
                $value = trim(trim($value), " \t\n\r\0\x0B\"'");
                if ($name !== '') {
                    $_ENV[$name] = $value;
                    putenv($name . '=' . $value);
                }
            }
        }
    }
}

header('Content-Type: application/json; charset=utf-8');

$question = $_POST['question'] ?? null;

if (!is_string($question) || trim($question) === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing question'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
$sanitizer = new Sanitizer();
$question = $sanitizer->sanitizeString($question);

// 1. Intentar usar RAG_BASE_URL (lo más limpio)
$ragBase = $_ENV['RAG_BASE_URL'] ?? getenv('RAG_BASE_URL');

// 2. Si no existe, intentar deducirlo de RAG_SERVICE_URL quitando /rag/heroes
if (!is_string($ragBase) || trim($ragBase) === '') {
    $serviceUrl = $_ENV['RAG_SERVICE_URL'] ?? getenv('RAG_SERVICE_URL') ?? '';
    $ragBase = str_replace('/rag/heroes', '', $serviceUrl);
}

// 3. Si seguimos sin base, detección por Host
if (!is_string($ragBase) || trim($ragBase) === '') {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $host = is_string($host) ? strtolower($host) : '';
    
    if (str_contains($host, 'staging.contenido.creawebes.com')) {
        $ragBase = 'https://rag-staging.contenido.creawebes.com';
    } elseif (str_contains($host, 'creawebes.com')) {
        $ragBase = 'https://rag-service.contenido.creawebes.com';
    } else {
        $ragBase = 'http://localhost:8082';
    }
}

// URL final siempre contra el endpoint de AGENTE
$ragUrl = rtrim((string)$ragBase, '/') . '/rag/agent';

$payload = ['question' => $question];
try {
    (new JsonValidator())->validate($payload, [
        'question' => ['type' => 'string', 'required' => true],
    ], allowEmpty: false);
} catch (\InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
$encodedPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($encodedPayload === false) {
    http_response_code(400);
    echo json_encode(['error' => 'Payload inválido'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$internalKey = trim((string) ($_ENV['INTERNAL_API_KEY'] ?? getenv('INTERNAL_API_KEY') ?? ''));
$caller = is_string($_SERVER['HTTP_HOST'] ?? null) ? trim((string) $_SERVER['HTTP_HOST']) : 'clean-marvel-app';
$signer = $internalKey !== '' ? new InternalRequestSigner($internalKey, $caller !== '' ? $caller : 'clean-marvel-app') : null;
$headers = $signer ? $signer->sign('POST', $ragUrl, $encodedPayload) : [];

$client = new CurlHttpClient();
$start = microtime(true);

try {
    $response = $client->postJson(
        $ragUrl,
        $encodedPayload,
        $headers,
        timeoutSeconds: 20,
        retries: 2
    );
    log_microservice_call($ragUrl, $response->statusCode, microtime(true) - $start, null);
    http_response_code($response->statusCode);
    echo $response->body;
} catch (\Throwable $exception) {
    log_microservice_call($ragUrl, 502, microtime(true) - $start, $exception->getMessage());
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * @param int|string|null $error
 */
function log_microservice_call(string $targetUrl, int $status, float $durationSeconds, $error = null): void
{
    $logFile = dirname(__DIR__, 2) . '/storage/logs/microservice_calls.log';
    $directory = dirname($logFile);
    if (!is_dir($directory)) {
        @mkdir($directory, 0775, true);
    }

    $entry = [
        'timestamp' => date('c'),
        'target' => $targetUrl,
        'status' => $status,
        'duration_ms' => (int) round($durationSeconds * 1000),
        'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'caller' => $_SERVER['HTTP_HOST'] ?? null,
        'trace_id' => $_SERVER['X_TRACE_ID'] ?? null,
    ];

    if ($error !== null && $error !== '') {
        $entry['error'] = substr((string) $error, 0, 400);
    }

    $encoded = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($encoded !== false) {
        @file_put_contents($logFile, $encoded . PHP_EOL, FILE_APPEND);
    }
}
