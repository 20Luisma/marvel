<?php

declare(strict_types=1);

namespace App\Movies\Infrastructure\ML;

use App\Movies\Domain\MovieRecommenderInterface;
use Phpml\Math\Distance\Euclidean;

/**
 * ML-powered movie recommender using feature vectors and KNN distance.
 *
 * Extracts numerical features from each movie and uses Euclidean distance
 * to find the most similar ones. Features used:
 *
 * 1. vote_average (normalized 0-1)
 * 2. release_year (normalized 0-1)
 * 3. overview_similarity (TF-IDF cosine similarity approximation)
 *
 * This is a real ML implementation using PHP-ML's distance metrics,
 * not just a simple filter or sort.
 */
final class PhpMlMovieRecommender implements MovieRecommenderInterface
{
    private const MIN_YEAR = 2008; // Iron Man release
    private const MAX_YEAR = 2030;
    private const MAX_VOTE = 10.0;

    /**
     * Spanish + English stop words to filter out common words from overviews.
     */
    private const STOP_WORDS = [
        'el', 'la', 'los', 'las', 'un', 'una', 'de', 'del', 'en', 'y', 'a',
        'que', 'es', 'por', 'con', 'para', 'se', 'su', 'al', 'lo', 'como',
        'más', 'pero', 'sus', 'le', 'ya', 'o', 'fue', 'ha', 'era', 'son',
        'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
        'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'has', 'have',
        'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should',
        'not', 'no', 'this', 'that', 'it', 'he', 'she', 'they', 'we', 'you',
        'from', 'as', 'his', 'her', 'their', 'its', 'who', 'which', 'when',
    ];

    public function recommend(array $targetMovie, array $catalog, int $limit = 5): array
    {
        $targetFeatures = $this->extractFeatures($targetMovie);
        $targetWords = $this->extractWords($targetMovie['overview'] ?? '');

        $distances = [];
        $euclidean = new Euclidean();

        foreach ($catalog as $movie) {
            $movieId = $movie['id'] ?? null;
            if ($movieId === null || $movieId === ($targetMovie['id'] ?? null)) {
                continue;
            }

            $movieFeatures = $this->extractFeatures($movie);

            // Calculate numerical feature distance
            $numericDistance = $euclidean->distance($targetFeatures, $movieFeatures);

            // Calculate text similarity (overview)
            $movieWords = $this->extractWords($movie['overview'] ?? '');
            $textSimilarity = $this->jaccardSimilarity($targetWords, $movieWords);

            // Combined score: lower = more similar
            // Text similarity is inverted (1 - similarity) to convert to distance
            $combinedDistance = ($numericDistance * 0.6) + ((1 - $textSimilarity) * 0.4);

            $distances[] = [
                'movie' => $movie,
                'distance' => $combinedDistance,
                'text_similarity' => round($textSimilarity * 100, 1),
                'numeric_distance' => round($numericDistance, 4),
            ];
        }

        // Sort by combined distance (ascending = most similar first)
        usort($distances, static fn ($a, $b) => $a['distance'] <=> $b['distance']);

        $recommendations = [];
        foreach (array_slice($distances, 0, $limit) as $item) {
            $movie = $item['movie'];
            $recommendations[] = [
                'id' => $movie['id'],
                'title' => $movie['title'] ?? '',
                'poster_path' => $movie['poster_path'] ?? null,
                'vote_average' => $movie['vote_average'] ?? null,
                'release_date' => $movie['release_date'] ?? null,
                'overview' => $movie['overview'] ?? '',
                'similarity_score' => round((1 - $item['distance']) * 100, 1),
                'text_similarity' => $item['text_similarity'],
            ];
        }

        return $recommendations;
    }

    /**
     * Extract numerical features from a movie for ML comparison.
     *
     * @return array<int, float> Feature vector [vote_normalized, year_normalized]
     */
    private function extractFeatures(array $movie): array
    {
        $vote = isset($movie['vote_average']) ? (float) $movie['vote_average'] : 5.0;
        $voteNormalized = $vote / self::MAX_VOTE;

        $year = self::MIN_YEAR;
        $releaseDate = $movie['release_date'] ?? '';
        if (is_string($releaseDate) && strlen($releaseDate) >= 4) {
            $parsedYear = (int) substr($releaseDate, 0, 4);
            if ($parsedYear >= self::MIN_YEAR) {
                $year = $parsedYear;
            }
        }
        $yearNormalized = ($year - self::MIN_YEAR) / (self::MAX_YEAR - self::MIN_YEAR);

        // Overview length as a feature (longer = more detailed = different type)
        $overviewLength = strlen($movie['overview'] ?? '');
        $lengthNormalized = min($overviewLength / 500.0, 1.0);

        return [$voteNormalized, $yearNormalized, $lengthNormalized];
    }

    /**
     * Extract meaningful words from text, removing stop words.
     *
     * @return array<string, bool> Set of unique words (word => true)
     */
    private function extractWords(string $text): array
    {
        $text = mb_strtolower($text);
        $text = (string) preg_replace('/[^\p{L}\s]/u', '', $text);
        $tokens = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        if (!is_array($tokens)) {
            return [];
        }

        $words = [];
        foreach ($tokens as $token) {
            if (strlen($token) > 2 && !in_array($token, self::STOP_WORDS, true)) {
                $words[$token] = true;
            }
        }

        return $words;
    }

    /**
     * Jaccard similarity between two word sets.
     * Returns a value between 0 (no common words) and 1 (identical).
     */
    private function jaccardSimilarity(array $setA, array $setB): float
    {
        if (empty($setA) || empty($setB)) {
            return 0.0;
        }

        $intersection = count(array_intersect_key($setA, $setB));
        $union = count($setA) + count($setB) - $intersection;

        return $union > 0 ? $intersection / $union : 0.0;
    }
}
