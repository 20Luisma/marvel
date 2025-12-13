<?php

declare(strict_types=1);

namespace Creawebes\Rag\Infrastructure\Retrieval;

use Creawebes\Rag\Application\Contracts\RagTelemetryInterface;
use Creawebes\Rag\Application\Observability\NullRagTelemetry;
use Creawebes\Rag\Application\Rag\MarvelAgentRetrieverInterface;
use Creawebes\Rag\Application\Similarity\CosineSimilarity;
use Creawebes\Rag\Infrastructure\Knowledge\MarvelAgentKnowledgeBase;
use Creawebes\Rag\Infrastructure\EmbeddingStore;
use Creawebes\Rag\Application\Contracts\EmbeddingClientInterface;

final class VectorMarvelAgentRetriever implements MarvelAgentRetrieverInterface
{
    public function __construct(
        private readonly MarvelAgentKnowledgeBase $knowledgeBase,
        private readonly EmbeddingStore $embeddingStore,
        private readonly EmbeddingClientInterface $embeddingClient,
        private readonly MarvelAgentRetrieverInterface $fallback,
        private readonly CosineSimilarity $similarity,
        private readonly bool $useEmbeddings = false,
        private readonly bool $autoRefreshEmbeddings = false,
        private readonly RagTelemetryInterface $telemetry = new NullRagTelemetry(),
    ) {
    }

    /**
     * @return array<int, array{id: string, title: string, text: string}>
     */
    public function retrieve(string $question, int $limit = 3): array
    {
        $start = microtime(true);
        if ($this->useEmbeddings === false) {
            return $this->retrieveViaFallback($question, $limit, $start);
        }

        $kb = $this->knowledgeBase->all();
        if ($kb === [] || count($kb) < 1) {
            return $this->retrieveViaFallback($question, $limit, $start);
        }

        $vectors = $this->embeddingStore->loadAll();
        $ids = array_column($kb, 'id');
        $missing = array_values(array_diff($ids, array_keys($vectors)));

        if ($this->autoRefreshEmbeddings && $missing !== []) {
            $vectors = $vectors + $this->generateMissingEmbeddings($kb, $missing);
        }

        if ($vectors === []) {
            return $this->retrieveViaFallback($question, $limit, $start);
        }

        $queryVector = $this->embeddingClient->embedText($question);
        if ($queryVector === []) {
            return $this->retrieveViaFallback($question, $limit, $start);
        }

        $scored = [];
        foreach ($kb as $entry) {
            $id = $entry['id'];
            if (!isset($vectors[$id])) {
                continue;
            }

            $score = $this->similarity->dense($queryVector, $vectors[$id]);
            $scored[] = $entry + ['score' => $score];
        }

        if ($scored === []) {
            return $this->retrieveViaFallback($question, $limit, $start);
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

        return array_map(
            static fn (array $item): array => [
                'id' => $item['id'],
                'title' => $item['title'],
                'text' => $item['text'],
            ],
            $scored
        );
    }

    /**
     * @param array<int, array{id: string, title: string, text: string}> $kb
     * @param array<int, string> $missingIds
     * @return array<string, array<float>>
     */
    private function generateMissingEmbeddings(array $kb, array $missingIds): array
    {
        $texts = [];
        $idOrder = [];
        foreach ($kb as $entry) {
            if (in_array($entry['id'], $missingIds, true)) {
                $idOrder[] = $entry['id'];
                $texts[] = trim($entry['title'] . "\n\n" . $entry['text']);
            }
        }

        if ($texts === []) {
            return [];
        }

        $vectors = $this->embeddingClient->embedDocuments($texts);
        $generated = [];

        foreach ($idOrder as $index => $id) {
            if (isset($vectors[$index]) && is_array($vectors[$index])) {
                $generated[$id] = array_map('floatval', $vectors[$index]);
                $this->embeddingStore->saveOne($id, $generated[$id]);
            }
        }

        return $generated;
    }

    /**
     * @return array<int, array{id: string, title: string, text: string}>
     */
    private function retrieveViaFallback(string $question, int $limit, float $start): array
    {
        $result = $this->fallback->retrieve($question, $limit);

        $this->telemetry->log(
            'rag.retrieve.fallback',
            'fallback',
            (int) round((microtime(true) - $start) * 1000),
            $limit
        );

        return $result;
    }
}
