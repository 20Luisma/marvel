<?php

declare(strict_types=1);

namespace Creawebes\Rag\Infrastructure;

use Creawebes\Rag\Application\Contracts\EmbeddingClientInterface;
use Creawebes\Rag\Application\Contracts\KnowledgeBaseInterface;
use Creawebes\Rag\Application\Contracts\RagTelemetryInterface;
use Creawebes\Rag\Application\Contracts\RetrieverInterface;
use Creawebes\Rag\Application\Observability\NullRagTelemetry;
use Creawebes\Rag\Application\Similarity\CosineSimilarity;

/**
 * Retriever que prioriza embeddings y cae al retriever léxico cuando faltan vectores.
 */
final class VectorHeroRetriever implements RetrieverInterface
{
    public function __construct(
        private readonly KnowledgeBaseInterface $knowledgeBase,
        private readonly EmbeddingStore $embeddingStore,
        private readonly EmbeddingClientInterface $embeddingClient,
        private readonly RetrieverInterface $fallback,
        private readonly CosineSimilarity $similarity,
        private readonly bool $useEmbeddings = false,
        private readonly bool $autoRefreshEmbeddings = false,
        private readonly RagTelemetryInterface $telemetry = new NullRagTelemetry(),
    ) {
    }

    /**
     * @param array<int, string> $heroIds
     * @return array<int, array{heroId: string, nombre: string, contenido: string, score: float}>
     */
    public function retrieve(array $heroIds, string $question, int $limit = 5): array
    {
        $start = microtime(true);
        if ($this->useEmbeddings === false) {
            return $this->retrieveViaFallback($heroIds, $question, $limit, $start);
        }

        $trimmedQuestion = trim($question);
        if ($trimmedQuestion === '') {
            $trimmedQuestion = 'Compara sus atributos y resume el resultado';
        }

        $uniqueIds = array_values(array_unique($heroIds));
        $heroes = $this->knowledgeBase->findByIds($uniqueIds);
        if ($heroes === []) {
            return $this->retrieveViaFallback($heroIds, $question, $limit, $start);
        }

        $heroEmbeddings = $this->embeddingStore->loadByHeroIds(array_column($heroes, 'heroId'));
        $missingHeroIds = array_values(array_diff(array_column($heroes, 'heroId'), array_keys($heroEmbeddings)));

        if ($this->autoRefreshEmbeddings && $missingHeroIds !== []) {
            $generated = $this->generateMissingEmbeddings($heroes, $missingHeroIds);
            $heroEmbeddings = $heroEmbeddings + $generated;
        }

        if ($heroEmbeddings === [] || count($heroEmbeddings) < 2) {
            return $this->retrieveViaFallback($heroIds, $question, $limit, $start);
        }

        $queryVector = $this->embeddingClient->embedText($trimmedQuestion);
        if ($queryVector === []) {
            return $this->retrieveViaFallback($heroIds, $question, $limit, $start);
        }

        $scored = [];
        foreach ($heroes as $hero) {
            $heroId = $hero['heroId'];
            if (!isset($heroEmbeddings[$heroId])) {
                continue;
            }

            $score = $this->similarity->dense($queryVector, $heroEmbeddings[$heroId]);
            $scored[] = [
                'heroId' => $hero['heroId'],
                'nombre' => $hero['nombre'],
                'contenido' => $hero['contenido'],
                'score' => $score,
            ];
        }

        if ($scored === [] || count($scored) < 2) {
            return $this->retrieveViaFallback($heroIds, $question, $limit, $start);
        }

        usort(
            $scored,
            static fn (array $a, array $b): int => $b['score'] <=> $a['score']
        );

        if ($limit > 0) {
            $scored = array_slice($scored, 0, $limit);
        }

        $this->telemetry->log(
            'rag.retrieve',
            'vector',
            (int) round((microtime(true) - $start) * 1000),
            $limit
        );

        return $scored;
    }

    /**
     * @param array<int, array{heroId: string, nombre: string, contenido: string}> $heroes
     * @param array<int, string> $missingHeroIds
     * @return array<string, array<float>>
     */
    private function generateMissingEmbeddings(array $heroes, array $missingHeroIds): array
    {
        $texts = [];
        $idOrder = [];
        foreach ($heroes as $hero) {
            if (in_array($hero['heroId'], $missingHeroIds, true)) {
                $idOrder[] = $hero['heroId'];
                $texts[] = trim(($hero['nombre'] ?? '') . "\n\n" . ($hero['contenido'] ?? ''));
            }
        }

        if ($texts === []) {
            return [];
        }

        $vectors = $this->embeddingClient->embedDocuments($texts);

        $generated = [];
        foreach ($idOrder as $index => $heroId) {
            if (isset($vectors[$index]) && is_array($vectors[$index])) {
                $generated[$heroId] = array_map('floatval', $vectors[$index]);
                $this->embeddingStore->saveOne($heroId, $generated[$heroId]);
            }
        }

        return $generated;
    }

    /**
     * @param array<int, string> $heroIds
     * @return array<int, array{heroId: string, nombre: string, contenido: string, score: float}>
     */
    private function retrieveViaFallback(array $heroIds, string $question, int $limit, float $start): array
    {
        $result = $this->fallback->retrieve($heroIds, $question, $limit);

        $this->telemetry->log(
            'rag.retrieve.fallback',
            'fallback',
            (int) round((microtime(true) - $start) * 1000),
            $limit
        );

        return $result;
    }
}
