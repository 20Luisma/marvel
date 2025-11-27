<?php

declare(strict_types=1);

use App\Shared\Infrastructure\Http\CurlHttpClient;
use App\Shared\Infrastructure\Security\InternalRequestSigner;

$rootPath = dirname(__DIR__, 2);
require_once $rootPath . '/vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

$question = $_POST['question'] ?? null;

if (!is_string($question) || trim($question) === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing question'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$ragUrl = $_ENV['RAG_SERVICE_URL'] ?? getenv('RAG_SERVICE_URL');
if (!is_string($ragUrl) || trim($ragUrl) === '') {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $host = is_string($host) ? strtolower($host) : '';
    $ragUrl = str_contains($host, 'creawebes.com')
        ? 'https://rag-service.contenido.creawebes.com/rag/agent'
        : 'http://localhost:8082/rag/agent';
}

$payload = ['question' => $question];
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
    ];

    if ($error !== null && $error !== '') {
        $entry['error'] = substr((string) $error, 0, 400);
    }

    $encoded = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($encoded !== false) {
        @file_put_contents($logFile, $encoded . PHP_EOL, FILE_APPEND);
    }
}
