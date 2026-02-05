<?php

declare(strict_types=1);

namespace Creawebes\Rag\Application\Clients;

use Creawebes\Rag\Application\Contracts\HttpTransportInterface;
use Creawebes\Rag\Application\Contracts\LlmClientInterface;
use Creawebes\Rag\Application\Contracts\StructuredLoggerInterface;
use Creawebes\Rag\Application\Observability\NullStructuredLogger;
use Creawebes\Rag\Application\Resilience\CircuitBreaker;
use Creawebes\Rag\Application\Resilience\CircuitBreakerOpenException;
use JsonException;
use RuntimeException;

final class OpenAiHttpClient implements LlmClientInterface
{
    private const DEFAULT_MODEL = 'gpt-4o-mini';
    private string $tokensLogPath;
    private string $logFile;
    private readonly string $openAiEndpoint;
    private readonly ?string $internalApiKey;
    private readonly string $internalCaller;
    private readonly string $feature;
    private readonly bool $debugEnabled;
    private readonly StructuredLoggerInterface $logger;
    private readonly ?CircuitBreaker $circuitBreaker;
    private readonly HttpTransportInterface $transport;

    public function __construct(
        ?string $openAiEndpoint = null,
        string $feature = 'rag_service',
        ?HttpTransportInterface $transport = null,
        ?CircuitBreaker $circuitBreaker = null,
        ?StructuredLoggerInterface $logger = null,
    )
    {
        $this->resolveLogPath();
        $this->resolveCentralLogPath();
        $endpoint = $openAiEndpoint ?? $_ENV['OPENAI_SERVICE_URL'] ?? getenv('OPENAI_SERVICE_URL') ?: 'http://localhost:8081/v1/chat';
        $this->openAiEndpoint = rtrim($endpoint, '/');
        $key = $_ENV['INTERNAL_API_KEY'] ?? getenv('INTERNAL_API_KEY') ?: '';
        $this->internalApiKey = is_string($key) && trim($key) !== '' ? trim($key) : null;
        $callerCandidate = $_ENV['APP_HOST'] ?? getenv('APP_HOST') ?? ($_ENV['APP_URL'] ?? getenv('APP_URL') ?? ($_SERVER['HTTP_HOST'] ?? ''));
        $callerCandidate = is_string($callerCandidate) ? $callerCandidate : '';
        if ($callerCandidate === '' && isset($_ENV['RAG_SERVICE_URL'])) {
            $parsed = parse_url((string) $_ENV['RAG_SERVICE_URL'], PHP_URL_HOST);
            $callerCandidate = is_string($parsed) ? $parsed : '';
        }
        $this->internalCaller = $callerCandidate !== '' ? $callerCandidate : 'localhost:8082';
        $this->feature = $feature;
        $this->debugEnabled = filter_var($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG'), FILTER_VALIDATE_BOOL) === true;
        $this->logger = $logger ?? new NullStructuredLogger();
        $this->circuitBreaker = $circuitBreaker;
        $this->transport = $transport ?? new class implements HttpTransportInterface {
            public function post(string $url, array $headers, string $body, int $connectTimeoutSeconds, int $timeoutSeconds): array
            {
                $ch = curl_init($url);
                if ($ch === false) {
                    return ['response' => false, 'http_code' => 0, 'error' => 'curl_init failed'];
                }

                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeoutSeconds);
                curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutSeconds);

                $response = curl_exec($ch);
                $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = (string) curl_error($ch);
                curl_close($ch);

                return ['response' => $response, 'http_code' => $httpCode, 'error' => $error];
            }
        };
    }

    private function resolveLogPath(): void
    {
        $envPath = getenv('TOKENS_LOG_PATH');
        if (is_string($envPath) && $envPath !== '') {
            $this->tokensLogPath = $envPath;
            return;
        }

        // Fallback: Ruta relativa (rag-service/storage/ai/tokens.log)
        $this->tokensLogPath = __DIR__ . '/../../../storage/ai/tokens.log';
    }

    private function resolveCentralLogPath(): void
    {
        $envPath = getenv('AI_TOKENS_LOG_PATH') ?: '';
        if ($envPath !== '' && is_writable(dirname($envPath))) {
            $this->logFile = $envPath;
            return;
        }

        // Fallback: log central del proyecto principal (storage/ai/tokens.log)
        $this->logFile = __DIR__ . '/../../../../storage/ai/tokens.log';
    }

    public function ask(string $prompt): string
    {
        $state = $this->circuitBreaker?->beforeCall();
        if ($state === null) {
            $state = 'closed';
        }

        $messages = [
            [
                'role' => 'system',
                'content' => 'Eres un narrador profesional que describe comparaciones entre héroes. Mantienes un estilo directo, claro y apto para audio, sin emojis, sin símbolos y sin acentos épicos.',
            ],
            [
                'role' => 'user',
                'content' => $prompt,
            ],
        ];

        $payload = [
            'messages' => $messages,
            'model' => self::DEFAULT_MODEL,
        ];

        $encodedPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encodedPayload === false) {
            throw new RuntimeException('No se pudo codificar el payload para el microservicio OpenAI.');
        }

        $attempts = 0;
        $maxAttempts = 3;
        $response = false;
        $httpCode = 0;
        $error = '';
        $requestStart = microtime(true);

        try {
            while ($attempts < $maxAttempts) {
                $attempts++;

                $headers = ['Content-Type: application/json'];
                if ($this->internalApiKey !== null) {
                    $headers = array_merge($headers, $this->signatureHeaders($encodedPayload));
                }

                $result = $this->transport->post($this->openAiEndpoint, $headers, $encodedPayload, 10, 30);
                $response = $result['response'];
                $httpCode = $result['http_code'];
                $error = $result['error'];

                if ($response !== false && $httpCode > 0 && $httpCode < 500) {
                    break;
                }

                if ($attempts < $maxAttempts) {
                    usleep((int) (250000 * (2 ** ($attempts - 1))));
                }
            }
        } catch (CircuitBreakerOpenException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $this->circuitBreaker?->onFailure();
            $this->logger->log('llm.request', [
                'state' => $this->circuitBreaker?->getState() ?? $state,
                'ok' => false,
                'error' => $exception->getMessage(),
                'latency_ms' => (int) round((microtime(true) - $requestStart) * 1000),
            ]);
            throw $exception;
        }

        if ($response === false || $httpCode >= 500) {
            $this->circuitBreaker?->onFailure();
            $this->logger->log('llm.request', [
                'state' => $this->circuitBreaker?->getState() ?? $state,
                'ok' => false,
                'error' => $error !== '' ? $error : 'http_' . $httpCode,
                'latency_ms' => (int) round((microtime(true) - $requestStart) * 1000),
            ]);
            throw new RuntimeException('Microservicio OpenAI no disponible' . ($error !== '' ? ': ' . $error : ''));
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode((string) $response, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->circuitBreaker?->onFailure();
            $snippet = trim((string) substr((string) $response, 0, 200));
            $details = $snippet !== '' ? ' Contenido recibido: ' . $snippet : '';
            $this->logger->log('llm.request', [
                'state' => $this->circuitBreaker?->getState() ?? $state,
                'ok' => false,
                'error' => 'invalid_json',
                'latency_ms' => (int) round((microtime(true) - $requestStart) * 1000),
            ]);
            throw new RuntimeException('Respuesta no válida del microservicio de OpenAI.' . $details, 0, $exception);
        }

        if (isset($decoded['error'])) {
            $this->circuitBreaker?->onFailure();
            $errorValue = $decoded['error'];
            if (is_array($errorValue)) {
                $errorValue = $errorValue['message'] ?? json_encode($errorValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            if (!is_string($errorValue)) {
                $errorValue = 'Error desconocido';
            }

            $this->logger->log('llm.request', [
                'state' => $this->circuitBreaker?->getState() ?? $state,
                'ok' => false,
                'error' => $errorValue,
                'latency_ms' => (int) round((microtime(true) - $requestStart) * 1000),
            ]);
            throw new RuntimeException('Microservicio OpenAI no disponible: ' . $errorValue);
        }

        if (isset($decoded['ok'])) {
            if ($decoded['ok'] !== true) {
                $this->circuitBreaker?->onFailure();
                $message = $decoded['error'] ?? 'Error desconocido';
                if (is_array($message)) {
                    $message = $message['message'] ?? json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }

                if (!is_string($message)) {
                    $message = 'Error desconocido';
                }

                $this->logger->log('llm.request', [
                    'state' => $this->circuitBreaker?->getState() ?? $state,
                    'ok' => false,
                    'error' => $message,
                    'latency_ms' => (int) round((microtime(true) - $requestStart) * 1000),
                ]);
                throw new RuntimeException('Microservicio OpenAI no disponible: ' . $message);
            }

            $this->logUsage($decoded, (int) round((microtime(true) - $requestStart) * 1000));

            $contentKeys = ['content', 'answer', 'text'];
            foreach ($contentKeys as $key) {
                $value = $decoded[$key] ?? null;
                if (is_string($value) && trim($value) !== '') {
                    $this->circuitBreaker?->onSuccess();
                    $this->logger->log('llm.request', [
                        'state' => $this->circuitBreaker?->getState() ?? $state,
                        'ok' => true,
                        'latency_ms' => (int) round((microtime(true) - $requestStart) * 1000),
                    ]);
                    return trim($value);
                }
            }

            $raw = $decoded['raw'] ?? null;
            if (is_array($raw)) {
                $value = $raw['choices'][0]['message']['content'] ?? null;
                if (is_string($value) && trim($value) !== '') {
                    $this->circuitBreaker?->onSuccess();
                    $this->logger->log('llm.request', [
                        'state' => $this->circuitBreaker?->getState() ?? $state,
                        'ok' => true,
                        'latency_ms' => (int) round((microtime(true) - $requestStart) * 1000),
                    ]);
                    return trim($value);
                }
            }

            $this->circuitBreaker?->onFailure();
            $this->logger->log('llm.request', [
                'state' => $this->circuitBreaker?->getState() ?? $state,
                'ok' => false,
                'error' => 'missing_content',
                'latency_ms' => (int) round((microtime(true) - $requestStart) * 1000),
            ]);
            throw new RuntimeException('Respuesta del microservicio no contenía datos de comparación.');
        }

        // Formato directo de OpenAI (sin campo 'ok')
        $content = $decoded['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            $this->circuitBreaker?->onFailure();
            $this->logger->log('llm.request', [
                'state' => $this->circuitBreaker?->getState() ?? $state,
                'ok' => false,
                'error' => 'missing_content',
                'latency_ms' => (int) round((microtime(true) - $requestStart) * 1000),
            ]);
            throw new RuntimeException('La respuesta del modelo no contenía comparación.');
        }

        // Registrar uso también para formato directo
        $this->logUsage($decoded, (int) round((microtime(true) - $requestStart) * 1000));

        $this->circuitBreaker?->onSuccess();
        $this->logger->log('llm.request', [
            'state' => $this->circuitBreaker?->getState() ?? $state,
            'ok' => true,
            'latency_ms' => (int) round((microtime(true) - $requestStart) * 1000),
        ]);

        return trim($content);
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private function logUsage(array $decoded, int $latencyMs = 0): void
    {
        // No registrar uso si SKIP_TOKEN_LOG está activo (entornos de CI/CD)
        if (filter_var($_ENV['SKIP_TOKEN_LOG'] ?? getenv('SKIP_TOKEN_LOG'), FILTER_VALIDATE_BOOL)) {
            return;
        }

        $usage = $decoded['usage'] ?? $decoded['raw']['usage'] ?? null;
        if (!is_array($usage)) {
            if ($this->debugEnabled) {
                $this->logger->log('llm.debug', [
                    'message' => 'No usage found',
                    'feature' => $this->feature,
                ]);
            }
            return;
        }

        $model = $decoded['model'] ?? $decoded['raw']['model'] ?? self::DEFAULT_MODEL;

        $entry = [
            'ts' => date('c'),
            'feature' => $this->feature,
            'model' => $model,
            'endpoint' => 'chat.completions',
            'prompt_tokens' => (int)($usage['prompt_tokens'] ?? 0),
            'completion_tokens' => (int)($usage['completion_tokens'] ?? 0),
            'total_tokens' => (int)($usage['total_tokens'] ?? 0),
            'latency_ms' => $latencyMs,
            'tools_used' => 0,
            'success' => true,
            'error' => null,
            'user_id' => 'system',
            'context_size' => 0,
        ];

        // Use resolved path
        $logFile = $this->logFile;
        $logDir = dirname($logFile);

        $json = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return;
        }

        // Asegurar que el directorio existe
        try {
            set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
                throw new \ErrorException($message, 0, $severity, $file, $line);
            });

            if (!is_dir($logDir) && !mkdir($logDir, 0775, true) && !is_dir($logDir)) {
                throw new \RuntimeException('Failed to create tokens log directory: ' . $logDir);
            }
        } catch (\Throwable $e) {
            if ($this->debugEnabled) {
                $this->logger->log('llm.debug', [
                    'message' => 'Failed to create tokens log directory',
                    'dir' => $logDir,
                    'error' => $e->getMessage(),
                ]);
            }
            error_log('[rag-service][log_write_failed] ' . $e->getMessage());
            error_log('[rag-service][log_payload] ' . $json);
            return;
        } finally {
            restore_error_handler();
        }

        // Verificar permisos de escritura
        if (!is_writable($logDir)) {
            if ($this->debugEnabled) {
                $this->logger->log('llm.debug', [
                    'message' => 'Tokens log directory not writable',
                    'dir' => $logDir,
                ]);
            }
        }

        try {
            $result = file_put_contents($logFile, $json . PHP_EOL, FILE_APPEND | LOCK_EX);
            if ($result === false) {
                if ($this->debugEnabled) {
                    $error = error_get_last();
                    $this->logger->log('llm.debug', [
                        'message' => 'Failed to write tokens log',
                        'file' => $logFile,
                        'error' => $error['message'] ?? 'Unknown',
                    ]);
                }
                error_log('[rag-service][log_write_failed] Cannot write log file: ' . $logFile);
                error_log('[rag-service][log_payload] ' . $json);
            }
        } catch (\Throwable $e) {
            if ($this->debugEnabled) {
                $this->logger->log('llm.debug', [
                    'message' => 'Exception writing tokens log',
                    'file' => $logFile,
                    'error' => $e->getMessage(),
                ]);
            }
            error_log('[rag-service][log_write_failed] ' . $e->getMessage());
            error_log('[rag-service][log_payload] ' . $json);
        }
    }

    /**
     * @return array<int, string>
     */
    private function signatureHeaders(string $rawBody): array
    {
        $timestamp = time();
        $path = parse_url($this->openAiEndpoint, PHP_URL_PATH) ?: '/v1/chat';
        $canonical = "POST\n{$path}\n{$timestamp}\n" . hash('sha256', $rawBody);
        $signature = hash_hmac('sha256', $canonical, (string) $this->internalApiKey);

        return [
            'X-Internal-Signature: ' . $signature,
            'X-Internal-Timestamp: ' . $timestamp,
            'X-Internal-Caller: ' . $this->internalCaller,
        ];
    }
}
