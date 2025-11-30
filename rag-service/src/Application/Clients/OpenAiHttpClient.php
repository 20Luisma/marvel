<?php

declare(strict_types=1);

namespace Creawebes\Rag\Application\Clients;

use Creawebes\Rag\Application\Contracts\LlmClientInterface;
use JsonException;
use RuntimeException;

final class OpenAiHttpClient implements LlmClientInterface
{
    private const DEFAULT_MODEL = 'gpt-4o-mini';

    private readonly string $openAiEndpoint;
    private readonly ?string $internalApiKey;
    private readonly string $internalCaller;

    public function __construct(?string $openAiEndpoint = null)
    {
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
    }

    public function ask(string $prompt): string
    {
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

        while ($attempts < $maxAttempts) {
            $attempts++;
            $ch = curl_init($this->openAiEndpoint);
            if ($ch === false) {
                $error = 'No se pudo inicializar la petición al microservicio de OpenAI.';
                continue;
            }

            $headers = ['Content-Type: application/json'];
            if ($this->internalApiKey !== null) {
                $headers = array_merge($headers, $this->signatureHeaders($encodedPayload));
            }

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $encodedPayload);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($response !== false && $httpCode > 0 && $httpCode < 500) {
                break;
            }

            if ($attempts < $maxAttempts) {
                usleep((int) (250000 * (2 ** ($attempts - 1))));
            }
        }

        if ($response === false || $httpCode >= 500) {
            throw new RuntimeException('Microservicio OpenAI no disponible' . ($error !== '' ? ': ' . $error : ''));
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode((string) $response, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $snippet = trim((string) substr((string) $response, 0, 200));
            $details = $snippet !== '' ? ' Contenido recibido: ' . $snippet : '';
            throw new RuntimeException('Respuesta no válida del microservicio de OpenAI.' . $details, 0, $exception);
        }

        if (isset($decoded['error'])) {
            $errorValue = $decoded['error'];
            if (is_array($errorValue)) {
                $errorValue = $errorValue['message'] ?? json_encode($errorValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            if (!is_string($errorValue)) {
                $errorValue = 'Error desconocido';
            }

            throw new RuntimeException('Microservicio OpenAI no disponible: ' . $errorValue);
        }

        if (isset($decoded['ok'])) {
            if ($decoded['ok'] !== true) {
                $message = $decoded['error'] ?? 'Error desconocido';
                if (is_array($message)) {
                    $message = $message['message'] ?? json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }

                if (!is_string($message)) {
                    $message = 'Error desconocido';
                }

                throw new RuntimeException('Microservicio OpenAI no disponible: ' . $message);
            }

            $this->logUsage($decoded);

            $contentKeys = ['content', 'answer', 'text'];
            foreach ($contentKeys as $key) {
                $value = $decoded[$key] ?? null;
                if (is_string($value) && trim($value) !== '') {
                    return trim($value);
                }
            }

            $raw = $decoded['raw'] ?? null;
            if (is_array($raw)) {
                $value = $raw['choices'][0]['message']['content'] ?? null;
                if (is_string($value) && trim($value) !== '') {
                    return trim($value);
                }
            }

            throw new RuntimeException('Respuesta del microservicio no contenía datos de comparación.');
        }

        $content = $decoded['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            throw new RuntimeException('La respuesta del modelo no contenía comparación.');
        }

        return trim($content);
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private function logUsage(array $decoded): void
    {
        $usage = $decoded['usage'] ?? $decoded['raw']['usage'] ?? null;
        if (!is_array($usage)) {
            return;
        }

        $model = $decoded['model'] ?? $decoded['raw']['model'] ?? self::DEFAULT_MODEL;
        
        // Determine feature based on caller or context
        // Since this is the RAG service, it handles both 'compare_heroes' and 'marvel_agent'
        // We can't easily distinguish here without passing context, so we'll default to 'rag_service'
        // or try to guess based on prompt content if needed, but 'rag_service' is safer.
        $feature = 'rag_service';

        $entry = [
            'ts' => date('c'),
            'feature' => $feature,
            'model' => $model,
            'endpoint' => 'chat.completions',
            'prompt_tokens' => (int)($usage['prompt_tokens'] ?? 0),
            'completion_tokens' => (int)($usage['completion_tokens'] ?? 0),
            'total_tokens' => (int)($usage['total_tokens'] ?? 0),
            'latency_ms' => 0, // We don't track latency here yet, could add it
            'tools_used' => 0,
            'success' => true,
            'error' => null,
            'user_id' => 'system',
            'context_size' => 0,
        ];

        $logFile = __DIR__ . '/../../../../storage/ai/tokens.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
             @mkdir($logDir, 0755, true);
        }

        $json = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json !== false) {
            @file_put_contents($logFile, $json . PHP_EOL, FILE_APPEND | LOCK_EX);
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
