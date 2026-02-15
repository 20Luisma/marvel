<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Shared\Infrastructure\Http\HttpClientInterface;
use App\Shared\Infrastructure\Security\InternalRequestSigner;
use App\Http\RequestBodyReader;

final class RagProxyController
{
    private const DEFAULT_TIMEOUT = 20;
    private const DEFAULT_RETRIES = 2;

    private readonly ?InternalRequestSigner $signer;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $ragServiceUrl,
        ?string $internalToken
    ) {
        // @phpstan-ignore-next-line
        $callerOverride = trim((string) ($_ENV['INTERNAL_CALLER'] ?? getenv('INTERNAL_CALLER') ?? ''));
        $caller = $callerOverride;
        if ($caller === '') {
            // @phpstan-ignore-next-line
            $appUrl = trim((string) ($_ENV['APP_PUBLIC_URL'] ?? getenv('APP_PUBLIC_URL') ?? $_ENV['APP_URL'] ?? getenv('APP_URL') ?? ''));
            if ($appUrl !== '') {
                $parsedHost = parse_url($appUrl, PHP_URL_HOST);
                $caller = is_string($parsedHost) && $parsedHost !== '' ? $parsedHost : $appUrl;
            }
        }
        if ($caller === '') {
            $caller = is_string($_SERVER['HTTP_HOST'] ?? null) ? trim((string) $_SERVER['HTTP_HOST']) : 'clean-marvel-app';
        }
        $this->signer = is_string($internalToken) && $internalToken !== ''
            ? new InternalRequestSigner($internalToken, $caller !== '' ? $caller : 'clean-marvel-app')
            : null;
    }

    public function forwardHeroesComparison(): void
    {
        $this->forward('/rag/heroes', 'debug_rag_proxy.log', 'RAG');
    }

    public function forwardAgent(): void
    {
        $this->forward('/rag/agent', 'microservice_calls.log', 'AGENT');
    }

    private function forward(string $endpointSuffix, string $logFileName, string $contextName): void
    {
        $logFile = __DIR__ . '/../../storage/logs/' . $logFileName;
        $debugEnabled = $this->isDebugLoggingEnabled();
        $targetUrl = $this->ragServiceUrl;
        if (!str_ends_with(rtrim($targetUrl, '/'), rtrim($endpointSuffix, '/'))) {
            $targetUrl = rtrim($targetUrl, '/') . $endpointSuffix;
        }

        try {
            $rawBody = $_SERVER['MARVEL_RAW_BODY'] ?? RequestBodyReader::getRawBody();
            $rawBody = is_string($rawBody) ? $rawBody : '';

            if ($rawBody === '') {
                $this->jsonError('El cuerpo de la petición está vacío', 400, $contextName, $logFile, $debugEnabled);
                return;
            }

            $payload = json_decode($rawBody, true);
            if (!is_array($payload)) {
                // Si no es JSON, probamos si es x-www-form-urlencoded (como envía el frontend legacy)
                if (str_contains($rawBody, '=') || str_contains($rawBody, '&')) {
                    parse_str($rawBody, $formData);
                    $payload = $formData;
                }
            }

            if (!is_array($payload) || $payload === []) {
                $this->jsonError('El cuerpo no es un JSON válido ni formulario reconocido', 400, $contextName, $logFile, $debugEnabled);
                return;
            }

            // Normalización/Limpieza para asegurar que lleguen los campos esperados
            if ($contextName === 'RAG') {
                $question = $payload['question'] ?? '';
                $heroIdsRaw = $payload['heroIds'] ?? [];
                $heroIds = [];
                if (is_array($heroIdsRaw)) {
                    foreach ($heroIdsRaw as $id) {
                        $heroIds[] = (string) $id;
                    }
                }
                $payload = [
                    'question' => (string) $question,
                    'heroIds' => $heroIds
                ];
            }

            $encodedPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encodedPayload === false) {
                throw new \RuntimeException('No se pudo serializar el payload.');
            }

            $requestHeaders = $this->signer !== null
                ? $this->signer->sign('POST', $targetUrl, $encodedPayload)
                : [];

            $traceId = $_SERVER['X_TRACE_ID'] ?? null;
            if (is_string($traceId) && $traceId !== '') {
                $requestHeaders['X-Trace-Id'] = $traceId;
            }

            $start = microtime(true);
            $response = $this->httpClient->postJson(
                $targetUrl,
                $encodedPayload,
                $requestHeaders,
                timeoutSeconds: self::DEFAULT_TIMEOUT,
                retries: self::DEFAULT_RETRIES
            );
            $duration = microtime(true) - $start;

            if ($logFileName === 'microservice_calls.log') {
                $this->logMicroserviceCall($targetUrl, $response->statusCode, $duration);
            }

            http_response_code($response->statusCode);
            header('Content-Type: application/json; charset=utf-8');
            echo $response->body;

        } catch (\Throwable $e) {
            if ($debugEnabled) {
                file_put_contents($logFile, date('c') . " [$contextName] EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
            }

            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'estado' => 'error',
                'message' => 'Error interno en el proxy ' . $contextName . ': ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    private function jsonError(string $message, int $code, string $context, string $logFile, bool $debug): void
    {
        if ($debug) {
            file_put_contents($logFile, date('c') . " [$context] ERROR: $message\n", FILE_APPEND);
        }
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'estado' => 'error',
            'message' => $message
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function logMicroserviceCall(string $targetUrl, int $status, float $durationSeconds): void
    {
        $logFile = dirname(__DIR__, 2) . '/storage/logs/microservice_calls.log';
        $entry = [
            'timestamp' => date('c'),
            'target' => $targetUrl,
            'status' => $status,
            'duration_ms' => (int) round($durationSeconds * 1000),
            'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'trace_id' => $_SERVER['X_TRACE_ID'] ?? null,
        ];

        @file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
    }


    private function isDebugLoggingEnabled(): bool
    {
        $env = strtolower((string)($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? ''));
        if ($env === 'prod') {
            return (bool)($_ENV['DEBUG_RAG_PROXY'] ?? $_SERVER['DEBUG_RAG_PROXY'] ?? false);
        }

        return true;
    }
}
