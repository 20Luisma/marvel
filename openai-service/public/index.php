<?php

declare(strict_types=1);

$baseDir = __DIR__ . '/..';
$autoload = $baseDir . '/vendor/autoload.php';

if (!file_exists($autoload)) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'error' => 'Dependencias del microservicio ausentes. Ejecuta `composer install` dentro de `openai-service/`.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return;
}

require $autoload;

$envPath = $baseDir . '/.env';
if (is_readable($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines) {
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            if (strpos($line, '=') === false) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if ($name !== '') {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

$router = new Creawebes\OpenAI\Http\Router();
$router->handle($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
