<?php

declare(strict_types=1);

namespace Creawebes\Rag\Application\Clients;

use Creawebes\Rag\Application\Contracts\EmbeddingClientInterface;
use JsonException;
use RuntimeException;

final class OpenAiEmbeddingClient implements EmbeddingClientInterface
{
    private const MODEL = 'text-embedding-3-small';
    private const ENDPOINT = 'https://api.openai.com/v1/embeddings';

    public function embedText(string $text): array
    {
        $results = $this->embedDocuments([$text]);
        return $results[0] ?? [];
    }

    public function embedDocuments(array $texts): array
    {
        $apiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY') ?: '';
        if (!is_string($apiKey) || trim($apiKey) === '') {
            throw new RuntimeException('Falta la variable de entorno OPENAI_API_KEY para generar embeddings.');
        }

        $payload = [
            'input' => array_values($texts),
            'model' => self::MODEL,
        ];

        $ch = curl_init(self::ENDPOINT);
        if ($ch === false) {
            throw new RuntimeException('No se pudo inicializar la petición de embeddings a OpenAI.');
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode >= 500) {
            throw new RuntimeException('Servicio de embeddings no disponible' . ($error !== '' ? ': ' . $error : ''));
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode((string) $response, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $snippet = trim((string) substr((string) $response, 0, 200));
            $details = $snippet !== '' ? ' Contenido recibido: ' . $snippet : '';
            throw new RuntimeException('Respuesta no válida del servicio de embeddings.' . $details, 0, $exception);
        }

        if (!isset($decoded['data']) || !is_array($decoded['data'])) {
            throw new RuntimeException('La respuesta de embeddings no contenía datos.');
        }

        $vectors = [];
        foreach ($decoded['data'] as $entry) {
            $vector = $entry['embedding'] ?? null;
            if (is_array($vector)) {
                $vectors[] = array_map('floatval', $vector);
            }
        }

        if ($vectors === []) {
            throw new RuntimeException('No se pudieron leer los embeddings generados.');
        }

        return $vectors;
    }
}
