<?php

declare(strict_types=1);

namespace Src\Controllers;

use App\Shared\Infrastructure\Http\HttpClientInterface;
use App\Shared\Infrastructure\Security\InternalRequestSigner;
use Src\Controllers\Http\Request;

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
        $caller = is_string($_SERVER['HTTP_HOST'] ?? null) ? trim((string) $_SERVER['HTTP_HOST']) : 'clean-marvel-app';
        $this->signer = is_string($internalToken) && $internalToken !== ''
            ? new InternalRequestSigner($internalToken, $caller !== '' ? $caller : 'clean-marvel-app')
            : null;
    }

    public function forwardHeroesComparison(): void
    {
        $payload = Request::jsonBody();
        $encodedPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encodedPayload === false) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Payload inválido.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        $headers = $this->signer !== null
            ? $this->signer->sign('POST', $this->ragServiceUrl, $encodedPayload)
            : [];

        $start = microtime(true);
        try {
            $response = $this->httpClient->postJson(
                $this->ragServiceUrl,
                $encodedPayload,
                $headers,
                timeoutSeconds: self::DEFAULT_TIMEOUT,
                retries: self::DEFAULT_RETRIES
            );
            $this->logCall($response->statusCode, $start);
        } catch (\Throwable $exception) {
            $this->logCall(502, $start, $exception->getMessage());
            http_response_code(502);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'error' => 'Error al contactar el microservicio RAG.',
                'message' => $exception->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        http_response_code($response->statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo $response->body;
    }

    private function logCall(int $statusCode, float $startTime, ?string $error = null): void
    {
        $durationMs = (int) round((microtime(true) - $startTime) * 1000);
        $logFile = dirname(__DIR__, 2) . '/storage/logs/microservice_calls.log';
        $directory = dirname($logFile);
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $entry = [
            'timestamp' => date('c'),
            'target' => $this->ragServiceUrl,
            'status' => $statusCode,
            'duration_ms' => $durationMs,
            'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'caller' => $_SERVER['HTTP_HOST'] ?? null,
        ];

        if ($error !== null) {
            $entry['error'] = substr($error, 0, 400);
        }

        $encoded = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded !== false) {
            @file_put_contents($logFile, $encoded . PHP_EOL, FILE_APPEND);
        }
    }
}
