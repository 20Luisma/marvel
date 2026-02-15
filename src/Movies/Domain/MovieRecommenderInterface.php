<?php

declare(strict_types=1);

namespace App\Movies\Domain;

/**
 * Contract for movie recommendation engines.
 * Follows Clean Architecture: domain defines the interface,
 * infrastructure provides the ML implementation.
 */
interface MovieRecommenderInterface
{
    /**
     * Given a target movie and a catalog, return the most similar movies.
     *
     * @param array<string, mixed> $targetMovie The movie to find similars for
     * @param array<int, array<string, mixed>> $catalog All available movies
     * @param int $limit Max recommendations to return
     * @return array<int, array<string, mixed>> Recommended movies with similarity score
     */
    public function recommend(array $targetMovie, array $catalog, int $limit = 5): array;
}
