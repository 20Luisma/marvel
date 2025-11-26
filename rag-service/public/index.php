<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$container = require_once __DIR__ . '/../src/bootstrap.php';

/**
 * @return array<int, string>
 */
function resolve_allowed_origins(): array
{
    $configured = $_ENV['ALLOWED_ORIGINS'] ?? getenv('ALLOWED_ORIGINS') ?: null;
    if (is_string($configured) && trim($configured) !== '') {
        $entries = array_filter(array_map('trim', explode(',', $configured)));
        if ($entries !== []) {
            return array_values($entries);
        }
    }

    return [
        'http://localhost:8080',
        'https://iamasterbigschool.contenido.creawebes.com',
        'https://rag-service.contenido.creawebes.com',
    ];
}

$allowedOrigins = resolve_allowed_origins();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
$origin = is_string($requestOrigin) ? trim($requestOrigin) : '';

header('Vary: Origin');

if ($origin !== '' && !in_array($origin, $allowedOrigins, true)) {
    if ($method === 'OPTIONS') {
        http_response_code(403);
    } else {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Origin not allowed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    return;
}

$allowedOrigin = $origin !== '' ? $origin : ($allowedOrigins[0] ?? '');
if ($allowedOrigin !== '') {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($method === 'POST' && $path === '/rag/heroes') {
    $controller = $container['ragController'] ?? null;
    if ($controller instanceof \Creawebes\Rag\Controllers\RagController) {
        $controller->compareHeroes();
        return;
    }

    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Controlador RAG no disponible.'], JSON_UNESCAPED_UNICODE);
    return;
}

if ($method === 'POST' && $path === '/rag/agent') {
    $useCase = $container['askMarvelAgentUseCase'] ?? null;
    if ($useCase instanceof \Creawebes\Rag\Application\UseCase\AskMarvelAgentUseCase) {
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Payload inválido.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        $question = isset($data['question']) ? trim((string) $data['question']) : '';
        if ($question === '') {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'La pregunta es obligatoria.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        try {
            $response = $useCase->ask($question);
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        } catch (\Throwable $exception) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Error interno llamando al Marvel Agent.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }
    }

    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Caso de uso Marvel Agent no disponible.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return;
}

http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['error' => 'Ruta no encontrada.'], JSON_UNESCAPED_UNICODE);
