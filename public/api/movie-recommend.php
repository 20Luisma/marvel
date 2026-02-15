<?php

declare(strict_types=1);

/**
 * Movie Recommendation API Endpoint
 *
 * Uses ML (KNN + Jaccard similarity) to recommend similar Marvel movies.
 *
 * GET /api/movie-recommend.php?id=123&limit=5
 *
 * Response:
 * {
 *   "ok": true,
 *   "target": { "id": 123, "title": "Avengers: Endgame" },
 *   "recommendations": [
 *     { "id": 456, "title": "Avengers: Infinity War", "similarity_score": 87.5, ... }
 *   ],
 *   "ml_metadata": {
 *     "algorithm": "KNN + Jaccard",
 *     "features": ["vote_average", "release_year", "overview_similarity"],
 *     "library": "PHP-ML 0.10"
 *   }
 * }
 */

use App\Movies\Application\RecommendMoviesUseCase;
use App\Movies\Infrastructure\ML\PhpMlMovieRecommender;
use Dotenv\Dotenv;

// --- Bootstrap ---
$rootPath = dirname(__DIR__, 2);
$vendorAutoload = $rootPath . '/vendor/autoload.php';

if (is_file($vendorAutoload)) {
    require_once $vendorAutoload;
}

if (class_exists(Dotenv::class)) {
    Dotenv::createImmutable($rootPath)->safeLoad();
} else {
    $envFile = $rootPath . '/.env';
    if (is_file($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                if (!str_contains($line, '=')) {
                    continue;
                }
                [$name, $value] = explode('=', $line, 2);
                $name = trim($name);
                $value = trim(trim($value), "\"'");
                if ($name !== '') {
                    $_ENV[$name] = $value;
                    putenv($name . '=' . $value);
                }
            }
        }
    }
}

// --- CORS & Headers ---
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// --- Validate input ---
$movieId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT) ?: 5;

if ($movieId === null || $movieId === false || $movieId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing or invalid movie id parameter']);
    exit;
}

$limit = max(1, min($limit, 10));

// --- Fetch movies from TMDB ---
$tmdbApiKey = $_ENV['TMDB_API_KEY'] ?? getenv('TMDB_API_KEY') ?: '';

if ($tmdbApiKey === '') {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Missing TMDB_API_KEY']);
    exit;
}

$query = http_build_query([
    'api_key' => $tmdbApiKey,
    'with_companies' => 420, // Marvel Studios
    'language' => 'es-ES',
    'include_adult' => 'false',
    'sort_by' => 'popularity.desc',
    'page' => 1,
]);

$endpoint = 'https://api.themoviedb.org/3/discover/movie?' . $query;

$ch = curl_init($endpoint);
if ($ch === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to initialize HTTP client']);
    exit;
}

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!is_string($response) || $httpCode !== 200) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'Failed to fetch movies from TMDB']);
    exit;
}

$payload = json_decode($response, true);
if (!is_array($payload) || !isset($payload['results'])) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'Invalid TMDB response']);
    exit;
}

// Also fetch page 2 for more movie data (better recommendations)
$query2 = http_build_query([
    'api_key' => $tmdbApiKey,
    'with_companies' => 420,
    'language' => 'es-ES',
    'include_adult' => 'false',
    'sort_by' => 'popularity.desc',
    'page' => 2,
]);

$ch2 = curl_init('https://api.themoviedb.org/3/discover/movie?' . $query2);
if ($ch2 !== false) {
    curl_setopt_array($ch2, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response2 = curl_exec($ch2);
    $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);

    if (is_string($response2) && $httpCode2 === 200) {
        $payload2 = json_decode($response2, true);
        if (is_array($payload2) && isset($payload2['results'])) {
            $payload['results'] = array_merge($payload['results'], $payload2['results']);
        }
    }
}

$catalog = array_map(
    static fn($movie) => [
        'id' => isset($movie['id']) ? (int) $movie['id'] : null,
        'title' => $movie['title'] ?? $movie['name'] ?? '',
        'poster_path' => $movie['poster_path'] ?? null,
        'release_date' => $movie['release_date'] ?? null,
        'vote_average' => isset($movie['vote_average']) ? (float) $movie['vote_average'] : null,
        'overview' => $movie['overview'] ?? '',
    ],
    $payload['results']
);

// --- ML Recommendation ---
$recommender = new PhpMlMovieRecommender();
$useCase = new RecommendMoviesUseCase($recommender);
$result = $useCase->execute($movieId, $catalog, $limit);

// Add ML metadata
$result['ml_metadata'] = [
    'algorithm' => 'KNN (Euclidean) + Jaccard text similarity',
    'features' => ['vote_average', 'release_year', 'overview_length', 'overview_similarity'],
    'weights' => ['numeric' => 0.6, 'text' => 0.4],
    'library' => 'PHP-ML 0.10',
    'catalog_size' => count($catalog),
];

$statusCode = $result['ok'] ? 200 : 404;
http_response_code($statusCode);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
