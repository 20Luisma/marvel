<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Shared\Http\JsonResponse;
use App\Shared\Infrastructure\Http\HttpClientInterface;

final class MovieController
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?string $tmdbApiKey
    ) {
    }

    public function index(): void
    {
        if ($this->tmdbApiKey === null || $this->tmdbApiKey === '') {
            JsonResponse::error('Missing TMDB_API_KEY', 500);
            return;
        }

        $query = [
            'api_key' => $this->tmdbApiKey,
            'with_companies' => 420,
            'language' => 'es-ES',
            'include_adult' => 'false',
            'sort_by' => 'popularity.desc',
            'page' => 1,
        ];

        $endpoint = 'https://api.themoviedb.org/3/discover/movie?' . http_build_query($query);

        try {
            $response = $this->httpClient->get($endpoint);
            if ($response->statusCode !== 200) {
                JsonResponse::error('Error contacting TMDB', 502);
                return;
            }

            $payload = json_decode($response->body, true);
            if (!is_array($payload) || !isset($payload['results'])) {
                JsonResponse::error('Invalid TMDB response', 502);
                return;
            }

            $movies = array_map(
                static fn ($movie) => [
                    'id' => isset($movie['id']) ? (int) $movie['id'] : null,
                    'title' => $movie['title'] ?? $movie['name'] ?? '',
                    'poster_path' => $movie['poster_path'] ?? null,
                    'release_date' => $movie['release_date'] ?? $movie['first_air_date'] ?? null,
                    'vote_average' => isset($movie['vote_average']) ? (float) $movie['vote_average'] : null,
                    'overview' => $movie['overview'] ?? '',
                ],
                $payload['results']
            );

            echo json_encode([
                'ok' => true,
                'results' => $movies,
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            JsonResponse::error('Error: ' . $e->getMessage(), 502);
        }
    }
}
