<?php

declare(strict_types=1);

namespace Creawebes\Rag\Infrastructure\Retrieval;

use Creawebes\Rag\Application\Contracts\EmbeddingClientInterface;
use Creawebes\Rag\Application\Contracts\RagTelemetryInterface;
use Creawebes\Rag\Application\Observability\NullRagTelemetry;
use Creawebes\Rag\Application\Rag\MarvelAgentRetrieverInterface;
use RuntimeException;
use Throwable;

/**
 * Implementación de RAG Enterprise utilizando Pinecone como base de datos vectorial.
 * 
 * Esta clase se encarga de:
 * 1. Vectorizar la consulta del usuario mediante OpenAI.
 * 2. Realizar una búsqueda de similitud semántica en la nube de Pinecone.
 * 3. Gestionar un fallback automático hacia un motor local si la nube no está disponible.
 */
final class PineconeMarvelAgentRetriever implements MarvelAgentRetrieverInterface
{
    private readonly string $apiKey;
    private readonly string $indexHost;

    public function __construct(
        private readonly EmbeddingClientInterface $embeddingClient,
        private readonly MarvelAgentRetrieverInterface $fallback,
        ?string $apiKey = null,
        ?string $indexHost = null,
        private readonly RagTelemetryInterface $telemetry = new NullRagTelemetry(),
    ) {
        $this->apiKey = $apiKey ?? $_ENV['PINECONE_API_KEY'] ?? getenv('PINECONE_API_KEY') ?: '';
        $this->indexHost = $indexHost ?? $_ENV['PINECONE_INDEX_HOST'] ?? getenv('PINECONE_INDEX_HOST') ?: '';
    }

    /**
     * @return array<int, array{id: string, title: string, text: string}>
     */
    public function retrieve(string $question, int $limit = 3): array
    {
        $start = microtime(true);

        if ($this->apiKey === '' || $this->indexHost === '') {
            return $this->retrieveViaFallback($question, $limit, $start);
        }

        try {
            // 1. Obtener el embedding de la pregunta
            $queryVector = $this->embeddingClient->embedText($question);
            if ($queryVector === []) {
                return $this->retrieveViaFallback($question, $limit, $start);
            }

            // 2. Consultar a Pinecone
            $results = $this->queryPinecone($queryVector, $limit);
            if ($results === []) {
                return $this->retrieveViaFallback($question, $limit, $start);
            }

            $this->telemetry->log(
                'rag.retrieve.pinecone',
                'enterprise_vector',
                (int) round((microtime(true) - $start) * 1000),
                $limit
            );

            return $results;

        } catch (Throwable $e) {
            error_log('[PineconeRetriever] Error: ' . $e->getMessage());
            return $this->retrieveViaFallback($question, $limit, $start);
        }
    }

    /**
     * @param array<int, float> $vector
     * @return array<int, array{id: string, title: string, text: string}>
     */
    private function queryPinecone(array $vector, int $limit): array
    {
        $url = rtrim($this->indexHost, '/') . '/query';
        $payload = [
            'vector' => $vector,
            'topK' => $limit,
            'includeMetadata' => true,
        ];

        $ch = curl_init($url);
        if ($ch === false) {
            return [];
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Api-Key: ' . $this->apiKey,
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !is_string($response)) {
            return [];
        }

        $decoded = json_decode($response, true);
        if (!isset($decoded['matches']) || !is_array($decoded['matches'])) {
            return [];
        }

        $results = [];
        foreach ($decoded['matches'] as $match) {
            $metadata = $match['metadata'] ?? [];
            $results[] = [
                'id' => $match['id'],
                'title' => $metadata['title'] ?? 'Sin título',
                'text' => $metadata['text'] ?? '',
            ];
        }

        return $results;
    }

    /**
     * @return array<int, array{id: string, title: string, text: string}>
     */
    private function retrieveViaFallback(string $question, int $limit, float $start): array
    {
        $result = $this->fallback->retrieve($question, $limit);

        $this->telemetry->log(
            'rag.retrieve.pinecone.fallback',
            'fallback',
            (int) round((microtime(true) - $start) * 1000),
            $limit
        );

        return $result;
    }
}
