<?php

declare(strict_types=1);

namespace Creawebes\Rag\Application\Similarity;

final class CosineSimilarity
{
    /**
     * @param array<int|float> $a
     * @param array<int|float> $b
     */
    public function dense(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return 0.0;
        }

        $length = min(count($a), count($b));
        $dot = 0.0;
        for ($i = 0; $i < $length; $i++) {
            $dot += (float) $a[$i] * (float) $b[$i];
        }

        $normA = sqrt(array_sum(array_map(
            static fn ($value): float => (float) $value * (float) $value,
            array_slice($a, 0, $length)
        )));
        $normB = sqrt(array_sum(array_map(
            static fn ($value): float => (float) $value * (float) $value,
            array_slice($b, 0, $length)
        )));

        if ($normA === 0.0 || $normB === 0.0) {
            return 0.0;
        }

        return $dot / ($normA * $normB);
    }

    /**
     * @param array<string, float> $a
     * @param array<string, float> $b
     */
    public function sparse(array $a, array $b): float
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

        $normA = sqrt(array_sum(array_map(static fn ($value): float => $value * $value, $a)));
        $normB = sqrt(array_sum(array_map(static fn ($value): float => $value * $value, $b)));

        if ($normA === 0.0 || $normB === 0.0) {
            return 0.0;
        }

        return $dot / ($normA * $normB);
    }
}

