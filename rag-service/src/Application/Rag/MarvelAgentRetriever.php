<?php

declare(strict_types=1);

namespace Creawebes\Rag\Application\Rag;

use Creawebes\Rag\Infrastructure\Knowledge\MarvelAgentKnowledgeBase;

final class MarvelAgentRetriever
{
    public function __construct(private readonly MarvelAgentKnowledgeBase $knowledgeBase)
    {
    }

    /**
     * @return array<int, array{id: string, title: string, text: string}>
     */
    public function retrieve(string $question, int $limit = 3): array
    {
        $normalizedQuestion = $this->normalize($question);
        $questionWords = $this->tokenize($normalizedQuestion);
        if ($questionWords === []) {
            return [];
        }

        $chunks = $this->knowledgeBase->all();
        $scored = [];

        foreach ($chunks as $chunk) {
            $textWords = $this->tokenize($this->normalize($chunk['title'] . ' ' . $chunk['text']));
            if ($textWords === []) {
                continue;
            }

            $matches = count(array_intersect($questionWords, $textWords));
            $score = $matches / count($questionWords);

            $scored[] = $chunk + ['score' => $score];
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

    private function normalize(string $text): string
    {
        $lower = mb_strtolower($text);
        $clean = preg_replace('/[^a-z0-9áéíóúüñ ]/u', ' ', $lower) ?? $lower;

        return trim($clean);
    }

    /**
     * @return array<int, string>
     */
    private function tokenize(string $text): array
    {
        $parts = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_filter($parts, static fn (string $word): bool => $word !== ''));
    }
}
