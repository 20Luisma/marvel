<?php

declare(strict_types=1);

namespace App\Movies\Application;

use App\Movies\Domain\MovieRecommenderInterface;

/**
 * Use Case: Recommend similar Marvel movies.
 *
 * Uses ML (K-Nearest Neighbors) to find movies similar to a given one,
 * based on vote_average, release_year, and text similarity of overviews.
 */
final class RecommendMoviesUseCase
{
    public function __construct(
        private readonly MovieRecommenderInterface $recommender
    ) {
    }

    /**
     * @param int $movieId Target movie ID
     * @param array<int, array<string, mixed>> $catalog All movies from TMDB
     * @param int $limit Number of recommendations
     * @return array{ok: bool, target: array<string, mixed>, recommendations: array<int, array<string, mixed>>}
     */
    public function execute(int $movieId, array $catalog, int $limit = 5): array
    {
        $targetMovie = null;
        foreach ($catalog as $movie) {
            if (($movie['id'] ?? null) === $movieId) {
                $targetMovie = $movie;
                break;
            }
        }

        if ($targetMovie === null) {
            return [
                'ok' => false,
                'error' => 'Movie not found in catalog',
                'recommendations' => [],
            ];
        }

        $recommendations = $this->recommender->recommend($targetMovie, $catalog, $limit);

        return [
            'ok' => true,
            'target' => [
                'id' => $targetMovie['id'],
                'title' => $targetMovie['title'] ?? '',
            ],
            'recommendations' => $recommendations,
        ];
    }
}
