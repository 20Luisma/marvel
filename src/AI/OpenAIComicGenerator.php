<?php

declare(strict_types=1);

namespace App\AI;

use App\Monitoring\TokenLogger;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

class OpenAIComicGenerator
{
    private const STORY_MODEL = 'gpt-4o-mini';
    private const DEFAULT_SERVICE_URL = 'http://localhost:8081/v1/chat';

    private readonly string $serviceUrl;
    private readonly ?string $internalApiKey;
    private readonly string $internalCaller;

    public function __construct(?string $serviceUrl = null)
    {
        $resolved = $serviceUrl;
        if ($resolved === null) {
            $envValue = $_ENV['OPENAI_SERVICE_URL'] ?? getenv('OPENAI_SERVICE_URL');
            if (is_string($envValue) && $envValue !== '') {
                $resolved = $envValue;
            }
        }

        if (!is_string($resolved) || trim((string) $resolved) === '') {
            $resolved = self::DEFAULT_SERVICE_URL;
        }

        $this->serviceUrl = rtrim($resolved, '/');
        $envInternalKey = $_ENV['INTERNAL_API_KEY'] ?? getenv('INTERNAL_API_KEY');
        $internalKey = is_string($envInternalKey) ? $envInternalKey : null;
        $this->internalApiKey = is_string($internalKey) && trim($internalKey) !== '' ? trim($internalKey) : null;

        $callerCandidate = '';
        foreach ([
            $_ENV['APP_HOST'] ?? null,
            getenv('APP_HOST') ?: null,
            $_ENV['APP_URL'] ?? null,
            getenv('APP_URL') ?: null,
            $_SERVER['HTTP_HOST'] ?? null,
        ] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                $callerCandidate = trim($candidate);
                break;
            }
        }

        $this->internalCaller = $callerCandidate !== '' ? $callerCandidate : 'clean-marvel-app';
    }

    public function isConfigured(): bool
    {
        return $this->serviceUrl !== '';
    }

    /**
     * @param array<int, array{heroId: string, nombre: string, contenido: string, imagen: string}> $heroes
     * @return array{
     *   story: array{title: string, summary: string, panels: array<int, array{title: string, description: string, caption: string}>}
     * }
     */
    public function generateComic(array $heroes): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('El microservicio de OpenAI no está configurado.');
        }

        if ($heroes === []) {
            throw new InvalidArgumentException('Debes proporcionar al menos un héroe para generar el cómic.');
        }

        $story = $this->generateStory($heroes);

        return [
            'story' => [
                'title' => $story['title'] ?? 'Cómic generado con IA',
                'summary' => $story['summary'] ?? '',
                'panels' => $story['panels'] ?? [],
            ],
        ];
    }

    /**
     * @param array<int, array{heroId: string, nombre: string, contenido: string, imagen: string}> $heroes
     * @return array{title: string, summary: string, panels: array<int, array{title: string, description: string, caption: string}>}
     */
    private function generateStory(array $heroes): array
    {
        $heroDescriptions = array_map(
            static fn (array $hero): string => sprintf(
                "- %s: %s",
                $hero['nombre'],
                $hero['contenido'] !== '' ? $hero['contenido'] : 'Sin descripción disponible.'
            ),
            $heroes
        );

        $heroList = implode("\n", $heroDescriptions);

        $messages = [
            [
                'role' => 'system',
                'content' => 'Eres un narrador profesional que trabaja con cómics. Hablas español neutro latino y describes escenas de forma directa, clara y apta para audio, sin emojis, sin símbolos decorativos ni onomatopeyas.',
            ],
            [
                'role' => 'user',
                'content' => sprintf(
                    <<<PROMPT
Genera una sinopsis y tres viñetas para un cómic corto protagonizado por los siguientes héroes:
%s

Instrucciones:
- Describe cada viñeta con un título breve, una descripción fluida y un caption que represente una línea de diálogo o una acción clara. Evita signos visuales innecesarios, símbolos o caracteres fuera del alfabeto estándar.
- Mantén un tono narrativo profesional, sin épica exagerada, sin estrellas ni emojis.
- Devuelve exactamente un objeto JSON válido con esta estructura:
{
  "title": "string",
  "summary": "string",
  "panels": [
    {
      "title": "string",
      "description": "string",
      "caption": "string"
    }
  ]
}
PROMPT,
                    $heroList
                ),
            ],
        ];

        $response = $this->requestChat($messages, self::STORY_MODEL);

        $content = $response['choices'][0]['message']['content'] ?? '';

        /** @var array{title?: string, summary?: string, panels?: array<int, array{title?: string, description?: string, caption?: string}>} $decoded */
        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('La IA devolvió una estructura inesperada al generar la historia.');
        }

        $panels = $decoded['panels'] ?? [];
        if (!is_array($panels)) {
            $panels = [];
        }

        return [
            'title' => (string) ($decoded['title'] ?? ''),
            'summary' => (string) ($decoded['summary'] ?? ''),
            'panels' => array_map(
                static fn (array $panel): array => [
                    'title' => (string) ($panel['title'] ?? ''),
                    'description' => (string) ($panel['description'] ?? ''),
                    'caption' => (string) ($panel['caption'] ?? ''),
                ],
                array_slice($panels, 0, 3)
            ),
        ];
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @return array<string, mixed>
     */
    private function requestChat(array $messages, ?string $model = null): array
    {
        $payload = [
            'messages' => $messages,
        ];
        $startedAt = microtime(true);

        if ($model !== null) {
            $payload['model'] = $model;
        }

        $encodedPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encodedPayload === false) {
            throw new RuntimeException('No se pudo serializar el payload para el microservicio de OpenAI.');
        }

        $attempts = 0;
        $maxAttempts = 3;
        $response = false;
        $httpCode = 0;
        $error = '';

        while ($attempts < $maxAttempts) {
            $attempts++;
            $ch = curl_init($this->serviceUrl);
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

        if (!is_string($response)) {
            throw new RuntimeException('Microservicio OpenAI devolvió una respuesta vacía o inesperada.');
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $snippet = trim((string) substr($response, 0, 200));
            $details = $snippet !== '' ? ' Contenido recibido: ' . $snippet : '';
            throw new RuntimeException('Respuesta no válida del microservicio de OpenAI.' . $details, 0, $exception);
        }

        $this->logUsageIfAvailable($decoded, $model, $startedAt);

        if (isset($decoded['error'])) {
            $error = $decoded['error'];

            if (is_array($error)) {
                $error = $error['message'] ?? json_encode($error, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            if (!is_string($error)) {
                $error = 'Error desconocido';
            }

            throw new RuntimeException('Microservicio OpenAI no disponible: ' . $error);
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

            $raw = $decoded['raw'] ?? null;
            if (is_array($raw)) {
                return $raw;
            }

            $contentKeys = ['content', 'story', 'text'];
            foreach ($contentKeys as $key) {
                $value = $decoded[$key] ?? null;
                if (is_string($value) && trim($value) !== '') {
                    return [
                        'choices' => [
                            [
                                'message' => [
                                    'content' => $value,
                                ],
                            ],
                        ],
                        'usage' => $decoded['usage'] ?? null,
                        'model' => $decoded['model'] ?? null,
                    ];
                }
            }

            throw new RuntimeException('Respuesta del microservicio no contenía datos de historia.');
        }

        return $decoded;
    }

    /**
     * @return array<int, string>
     */
    private function signatureHeaders(string $rawBody): array
    {
        $timestamp = time();
        $path = parse_url($this->serviceUrl, PHP_URL_PATH) ?: '/v1/chat';
        $canonical = "POST\n{$path}\n{$timestamp}\n" . hash('sha256', $rawBody);
        $signature = hash_hmac('sha256', $canonical, (string) $this->internalApiKey);

        return [
            'X-Internal-Signature: ' . $signature,
            'X-Internal-Timestamp: ' . $timestamp,
            'X-Internal-Caller: ' . $this->internalCaller,
        ];
    }

    /**
     * Registra uso de tokens siempre que el microservicio u OpenAI devuelvan la métrica.
     *
     * @param array<string, mixed> $decoded
     */
    private function logUsageIfAvailable(array $decoded, ?string $fallbackModel, float $startedAt): void
    {
        $latency = max(0, (int) round((microtime(true) - $startedAt) * 1000));

        $usage = $this->extractUsage($decoded);
        $model = $this->extractModel($decoded, $fallbackModel);
        $success = isset($decoded['ok']) ? (bool) $decoded['ok'] : true;
        $error = null;
        if (isset($decoded['error']) && is_string($decoded['error'])) {
            $error = $decoded['error'];
            $success = false;
        }

        try {
            TokenLogger::log([
                'feature' => 'comic_generator',
                'model' => $model,
                'endpoint' => 'chat.completions',
                'prompt_tokens' => (int)($usage['prompt_tokens'] ?? 0),
                'completion_tokens' => (int)($usage['completion_tokens'] ?? 0),
                'total_tokens' => (int)($usage['total_tokens'] ?? 0),
                'latency_ms' => $latency,
                'tools_used' => (int)($usage['tools_calls'] ?? 0),
                'success' => $success,
                'error' => $error,
                'user_id' => 'demo',
                'context_size' => 0,
            ]);
        } catch (\Throwable) {
            // No interrumpir el flujo si el log falla.
        }
    }

    /**
     * @param array<string, mixed> $decoded
     * @return array<string, mixed>
     */
    private function extractUsage(array $decoded): array
    {
        if (isset($decoded['usage']) && is_array($decoded['usage'])) {
            return $decoded['usage'];
        }

        if (isset($decoded['raw']['usage']) && is_array($decoded['raw']['usage'])) {
            return $decoded['raw']['usage'];
        }

        if (isset($decoded['data']['usage']) && is_array($decoded['data']['usage'])) {
            return $decoded['data']['usage'];
        }

        if (isset($decoded['data']['raw']['usage']) && is_array($decoded['data']['raw']['usage'])) {
            return $decoded['data']['raw']['usage'];
        }

        // Sin métricas explícitas, devolvemos estructura vacía para forzar creación de tokens.log
        return [
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
            'tools_calls' => 0,
        ];
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private function extractModel(array $decoded, ?string $fallbackModel): string
    {
        $candidates = [
            $decoded['model'] ?? null,
            $decoded['raw']['model'] ?? null,
            $decoded['data']['model'] ?? null,
            $decoded['data']['raw']['model'] ?? null,
            $fallbackModel,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return 'unknown';
    }
}
