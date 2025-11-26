<?php

declare(strict_types=1);

namespace Creawebes\Rag\Infrastructure\Retrieval;

use Creawebes\Rag\Application\Rag\MarvelAgentRetrieverInterface;
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
        private readonly bool $useEmbeddings = false,
        private readonly bool $autoRefreshEmbeddings = false,
    ) {
    }

    /**
     * @return array<int, array{id: string, title: string, text: string}>
     */
    public function retrieve(string $question, int $limit = 3): array
    {
        if ($this->useEmbeddings === false) {
            return $this->fallback->retrieve($question, $limit);
        }

        $kb = $this->knowledgeBase->all();
        if ($kb === [] || count($kb) < 1) {
            return $this->fallback->retrieve($question, $limit);
        }

        $vectors = $this->embeddingStore->loadAll();
        $ids = array_column($kb, 'id');
        $missing = array_values(array_diff($ids, array_keys($vectors)));

        if ($this->autoRefreshEmbeddings && $missing !== []) {
            $vectors = $vectors + $this->generateMissingEmbeddings($kb, $missing);
        }

        if ($vectors === []) {
            return $this->fallback->retrieve($question, $limit);
        }

        $queryVector = $this->embeddingClient->embedText($question);
        if ($queryVector === []) {
            return $this->fallback->retrieve($question, $limit);
        }

        $scored = [];
        foreach ($kb as $entry) {
            $id = $entry['id'];
            if (!isset($vectors[$id])) {
                continue;
            }

            $score = $this->cosineSimilarity($queryVector, $vectors[$id]);
            $scored[] = $entry + ['score' => $score];
        }

        if ($scored === []) {
            return $this->fallback->retrieve($question, $limit);
        }

        usort(
            $scored,
            static fn (array $a, array $b): int => $b['score'] <=> $a['score']
        );

        if ($limit > 0) {
            $scored = array_slice($scored, 0, $limit);
        }

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
     * @param array<int|float> $a
     * @param array<int|float> $b
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return 0.0;
        }

        $length = min(count($a), count($b));
        $dot = 0.0;
        for ($i = 0; $i < $length; $i++) {
            $dot += (float) $a[$i] * (float) $b[$i];
        }

        $normA = sqrt(array_sum(array_map(static fn ($value) => (float) $value * (float) $value, array_slice($a, 0, $length))));
        $normB = sqrt(array_sum(array_map(static fn ($value) => (float) $value * (float) $value, array_slice($b, 0, $length))));

        if ($normA === 0.0 || $normB === 0.0) {
            return 0.0;
        }

        return $dot / ($normA * $normB);
    }
}
