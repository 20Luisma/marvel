<?php

declare(strict_types=1);

namespace Creawebes\Rag\Application;

use Creawebes\Rag\Application\Contracts\KnowledgeBaseInterface;
use Creawebes\Rag\Application\Contracts\RetrieverInterface;

final class HeroRetriever implements RetrieverInterface
{
    public function __construct(private readonly KnowledgeBaseInterface $knowledgeBase)
    {
    }

    /**
     * @param array<int, string> $heroIds
     * @return array<int, array{heroId: string, nombre: string, contenido: string, score: float}>
     */
    public function retrieve(array $heroIds, string $question, int $limit = 5): array
    {
        $questionVector = $this->vectorize($question);

        $heroes = $this->knowledgeBase->findByIds(array_values(array_unique($heroIds)));
        $scored = [];

        foreach ($heroes as $hero) {
            $text = $hero['nombre'] . ' ' . $hero['contenido'];
            $vector = $this->vectorize($text);
            $score = $this->cosineSimilarity($questionVector, $vector);

            $scored[] = [
                'heroId' => $hero['heroId'],
                'nombre' => $hero['nombre'],
                'contenido' => $hero['contenido'],
                'score' => $score,
            ];
        }

        usort(
            $scored,
            static fn (array $a, array $b): int => $b['score'] <=> $a['score']
        );

        if ($limit > 0) {
            $scored = array_slice($scored, 0, $limit);
        }

        return $scored;
    }

    /**
     * @param array<string, float> $a
     * @param array<string, float> $b
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return 0.0;
        }

        $dot = 0.0;
        foreach ($a as $term => $weight) {
            if (isset($b[$term])) {
                $dot += $weight * $b[$term];
            }
        }

        $normA = sqrt(array_sum(array_map(static fn ($value) => $value * $value, $a)));
        $normB = sqrt(array_sum(array_map(static fn ($value) => $value * $value, $b)));

        if ($normA === 0.0 || $normB === 0.0) {
            return 0.0;
        }

        return $dot / ($normA * $normB);
    }

    /**
     * @return array<string, float>
     */
    private function vectorize(string $text): array
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-záéíóúüñ0-9 ]/u', ' ', $text) ?? $text;
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        $vector = [];
        foreach ($words as $word) {
            if (strlen($word) < 3) {
                continue;
            }

            $vector[$word] = ($vector[$word] ?? 0) + 1;
        }

        return $vector;
    }
}
