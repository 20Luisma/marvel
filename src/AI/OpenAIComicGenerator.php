<?php

declare(strict_types=1);

namespace App\AI;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

class OpenAIComicGenerator
{
    private const STORY_MODEL = 'gpt-4o-mini';
    private const DEFAULT_SERVICE_URL = 'http://localhost:8081/v1/chat';

    private readonly string $serviceUrl;

    public function __construct(?string $serviceUrl = null)
    {
        $resolved = $serviceUrl ?? ($_ENV['OPENAI_SERVICE_URL'] ?? null);
        if ($resolved === null) {
            $envValue = getenv('OPENAI_SERVICE_URL');
            if (is_string($envValue) && $envValue !== '') {
                $resolved = $envValue;
            }
        }

        if (!is_string($resolved) || trim($resolved) === '') {
            $resolved = self::DEFAULT_SERVICE_URL;
        }

        $this->serviceUrl = rtrim($resolved, '/');
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
                'content' => 'Eres un escritor y director creativo de cómics de Marvel. Generas historias épicas en español latino neutro.',
            ],
            [
                'role' => 'user',
                'content' => sprintf(
                    <<<PROMPT
Genera una sinopsis y viñetas para un cómic corto protagonizado por los siguientes héroes:
%s

Instrucciones:
- La historia debe tener un tono heroico y energético.
- Devuelve exactamente 3 viñetas numeradas, cada una con título breve, descripción narrativa y un caption corto con la línea de diálogo principal o onomatopeya.
- Toda la respuesta debe ser un objeto JSON que cumpla con el siguiente esquema:
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

        return [
            'title' => (string) ($decoded['title'] ?? ''),
            'summary' => (string) ($decoded['summary'] ?? ''),
            'panels' => array_map(
                static fn (array $panel): array => [
                    'title' => (string) ($panel['title'] ?? ''),
                    'description' => (string) ($panel['description'] ?? ''),
                    'caption' => (string) ($panel['caption'] ?? ''),
                ],
                array_slice(
                    array_filter(
                        $decoded['panels'] ?? [],
                        static fn ($panel): bool => is_array($panel)
                    ),
                    0,
                    3
                )
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

        if ($model !== null) {
            $payload['model'] = $model;
        }

        $ch = curl_init($this->serviceUrl);
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
            $decoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $snippet = trim((string) substr($response, 0, 200));
            $details = $snippet !== '' ? ' Contenido recibido: ' . $snippet : '';
            throw new RuntimeException('Respuesta no válida del microservicio de OpenAI.' . $details, 0, $exception);
        }

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
                    ];
                }
            }

            throw new RuntimeException('Respuesta del microservicio no contenía datos de historia.');
        }

        return $decoded;
    }
}
