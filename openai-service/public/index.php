<?php

declare(strict_types=1);

use Creawebes\OpenAI\Http\Router;

$baseDir  = dirname(__DIR__);
$autoload = $baseDir . '/vendor/autoload.php';

if (!file_exists($autoload)) {
    respondJson([
        'error' => 'Dependencias del microservicio ausentes. Ejecuta `composer install` dentro de `openai-service/`.'
    ], 500);
    return;
}

require_once $autoload;

$envPath = $baseDir . '/.env';
loadEnvIfAvailable($envPath);

$router = new Router();
$router->handle($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');

/**
 * @param array<string,mixed> $payload
 */
function respondJson(array $payload, int $status): void
{
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function loadEnvIfAvailable(string $envPath): void
{
    if (!is_readable($envPath)) {
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = array_map('trim', explode('=', $line, 2) + [1 => '']);
        if ($name === '') {
            continue;
        }

        putenv(sprintf('%s=%s', $name, $value));
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}
