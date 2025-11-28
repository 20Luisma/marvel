<?php

declare(strict_types=1);

namespace Src\Controllers;

use App\Shared\Infrastructure\Http\HttpClientInterface;
use App\Shared\Infrastructure\Security\InternalRequestSigner;
use App\Security\Sanitizer;
use App\Security\Validation\InputSanitizer;
use App\Security\Validation\JsonValidator;
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
        $sanitizer = new Sanitizer();
        $inputSanitizer = new InputSanitizer();
        $validator = new JsonValidator();
        try {
            $validator->validate($payload, [
                'heroIds' => ['type' => 'array', 'required' => true],
                'question' => ['type' => 'string', 'required' => false],
            ], allowEmpty: false);

            if (!is_array($payload['heroIds'] ?? null)) {
                throw new \InvalidArgumentException('El campo heroIds es obligatorio.');
            }
            $heroIds = $payload['heroIds'];
            if (count($heroIds) < 2) {
                throw new \InvalidArgumentException('Debes enviar al menos dos héroes.');
            }
            foreach ($heroIds as $idx => $value) {
                if (!is_string($value)) {
                    throw new \InvalidArgumentException('heroIds debe ser arreglo de strings.');
                }
                $heroIds[$idx] = $inputSanitizer->sanitizeString($value, 255);
            }
            $payload['heroIds'] = $heroIds;
        } catch (\InvalidArgumentException $exception) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        $sanitizer = new Sanitizer();
        if (isset($payload['question']) && is_string($payload['question'])) {
            $originalQuestion = $payload['question'];
            $payload['question'] = $inputSanitizer->sanitizeString($originalQuestion, 1000);
            if ($payload['question'] === '' || strlen($payload['question']) > 1000) {
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'La pregunta es demasiado larga.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                return;
            }
            if ($inputSanitizer->isSuspicious($originalQuestion)) {
                $this->logSuspicious('question');
            }
        }
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
            'trace_id' => $_SERVER['X_TRACE_ID'] ?? null,
        ];

        if ($error !== null) {
            $entry['error'] = substr($error, 0, 400);
        }

        $encoded = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded !== false) {
            @file_put_contents($logFile, $encoded . PHP_EOL, FILE_APPEND);
        }
    }

    private function logSuspicious(string $field): void
    {
        $logger = $GLOBALS['__clean_marvel_container']['security']['logger'] ?? null;
        if ($logger instanceof \App\Security\Logging\SecurityLogger) {
            $logger->logEvent('payload_suspicious', [
                'trace_id' => $_SERVER['X_TRACE_ID'] ?? null,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'path' => '/api/rag/heroes',
                'field' => $field,
            ]);
        }
    }
}
