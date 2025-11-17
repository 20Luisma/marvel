<?php

declare(strict_types=1);

use Dotenv\Dotenv;

if (stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html') !== false) {
    header('Location: /movies');
    exit;
}

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
                $value = trim($value);
                $value = trim($value, " \t\n\r\0\x0B\"'");

                if ($name !== '') {
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                    putenv($name . '=' . $value);
                }
            }
        }
    }
}

require_once $rootPath . '/src/bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, max-age=0');

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'message' => 'Método no permitido. Usa GET para este endpoint.',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$apiKey = trim((string) (getenv('TMDB_API_KEY') ?: ($_ENV['TMDB_API_KEY'] ?? '')));

if ($apiKey === '') {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Missing TMDB_API_KEY',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$query = http_build_query([
    'api_key' => $apiKey,
    'with_companies' => 420,
    'language' => 'es-ES',
    'include_adult' => 'false',
    'sort_by' => 'popularity.desc',
    'page' => 1,
]);

$endpoint = 'https://api.themoviedb.org/3/discover/movie?' . $query;
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 10,
        'header' => "Accept: application/json\r\n",
    ],
]);

$response = @file_get_contents($endpoint, false, $context);
if ($response === false) {
    http_response_code(502);
    echo json_encode([
        'ok' => false,
        'message' => 'Error contacting TMDB',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$payload = json_decode($response, true);
if (!is_array($payload) || !isset($payload['results']) || !is_array($payload['results'])) {
    http_response_code(502);
    echo json_encode([
        'ok' => false,
        'message' => 'Invalid TMDB response',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
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
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
