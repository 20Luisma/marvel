<?php

declare(strict_types=1);

namespace Creawebes\Rag\Application;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

final class HeroRagService
{
    private const DEFAULT_QUESTION = 'Compara sus atributos y resume el resultado';
    private const DEFAULT_MODEL = 'gpt-4o-mini';

    private readonly string $openAiEndpoint;

    public function __construct(
        private readonly HeroRetriever $retriever,
        ?string $openAiEndpoint = null
    ) {
        $endpoint = $openAiEndpoint ?? $_ENV['OPENAI_SERVICE_URL'] ?? getenv('OPENAI_SERVICE_URL') ?: 'http://localhost:8081/v1/chat';
        $this->openAiEndpoint = rtrim($endpoint, '/');
    }

    /**
     * @param array<int, string> $heroIds
     * @return array{answer: string, contexts: array<int, array{heroId: string, nombre: string, contenido: string, score: float}>, heroIds: array<int, string>}
     */
    public function compare(array $heroIds, ?string $question = null): array
    {
        $question = trim((string) ($question ?? self::DEFAULT_QUESTION));
        if ($question === '') {
            $question = self::DEFAULT_QUESTION;
        }

        $count = count($heroIds);
        if ($count !== 2) {
            throw new InvalidArgumentException('Selecciona 2 héroes primero.');
        }

        $contexts = $this->retriever->retrieve($heroIds, $question);
        if (count($contexts) < 2) {
            throw new InvalidArgumentException('Selecciona 2 héroes primero.');
        }

        $answer = $this->askOpenAI($contexts, $question);

        return [
            'answer' => $answer,
            'contexts' => $contexts,
            'heroIds' => array_column($contexts, 'heroId'),
        ];
    }

    /**
     * @param array<int, array{heroId: string, nombre: string, contenido: string}> $contexts
     */
    private function buildPrompt(array $contexts, string $question): string
    {
        $heroSummaries = array_map(
            static fn (array $context): string => sprintf(
                "- %s (ID: %s)\n  Contexto: %s",
                $context['nombre'] !== '' ? $context['nombre'] : 'Héroe sin nombre',
                $context['heroId'],
                $context['contenido'] !== '' ? $context['contenido'] : 'Sin descripción disponible'
            ),
            $contexts
        );

        $joined = implode("\n", $heroSummaries);

        return sprintf(
            <<<PROMPT
Usa los siguientes contextos de héroes para responder la pregunta: "%s".

%s

Requisitos de formato:
- Cada contexto incluye líneas con el formato `Atributo\tValoración`. Extrae solo esos atributos para hacer una tabla en Markdown con encabezado `Atributo | {nombre héroe A} | {nombre héroe B}`.
- Mantén exactamente el nombre del atributo y la valoración (estrellas, texto, paréntesis) tal como aparecen en el contexto. No inventes filas ni modifiques puntuaciones.
- Incluye al menos 5 atributos si existen en el contexto; si hay más, prioriza los más relevantes para combate, defensa, habilidades especiales e inteligencia táctica.
- Después de la tabla, escribe una sección llamada `Conclusión narrativa` compuesta por **dos párrafos** de 3 a 4 frases cada uno, con tono épico y descriptivo.
  - En el primer párrafo, señala atributo por atributo quién lidera comparando directamente las valoraciones (ejemplo: "Hulk domina en Poder de ataque ★★★★★ frente a Iron Man ★★★★☆").
  - En el segundo párrafo, explica cómo se complementan en combate citando al menos **dos atributos** por héroe y remata con un emoji.
- Todo debe basarse exclusivamente en los datos del contexto; no inventes nuevos atributos ni cambies las valoraciones originales.
- Usa vocabulario colorido del universo Marvel, evitando frases genéricas o repetidas literalmente del contexto.
PROMPT,
            $question,
            $joined
        );
    }

    /**
     * @param array<int, array{heroId: string, nombre: string, contenido: string, score: float}> $contexts
     */
    private function askOpenAI(array $contexts, string $question): string
    {
        $prompt = $this->buildPrompt($contexts, $question);

        $messages = [
            [
                'role' => 'system',
                'content' => 'Eres un analista experto en cómics de Marvel. Comparas héroes en español neutral latino con rigor y creatividad.',
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
