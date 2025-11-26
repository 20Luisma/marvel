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

    public function __construct(?string $openAiEndpoint = null)
    {
        $endpoint = $openAiEndpoint ?? $_ENV['OPENAI_SERVICE_URL'] ?? getenv('OPENAI_SERVICE_URL') ?: 'http://localhost:8081/v1/chat';
        $this->openAiEndpoint = rtrim($endpoint, '/');
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

        $ch = curl_init($this->openAiEndpoint);
        if ($ch === false) {
            throw new RuntimeException('No se pudo inicializar la petición al microservicio de OpenAI.');
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

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
}
