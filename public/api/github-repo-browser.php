<?php

declare(strict_types=1);

use App\Services\GithubClient;

$rootPath = dirname(__DIR__, 2);
require_once $rootPath . '/vendor/autoload.php';

if (!function_exists('shouldExit')) {
    function shouldExit(): bool
    {
        return !defined('GITHUB_REPO_BROWSER_TEST') || GITHUB_REPO_BROWSER_TEST !== true;
    }
}

if (!function_exists('jsonResponse')) {
    function jsonResponse(int $status, array $payload): void
    {
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        if (shouldExit()) {
            exit;
        }
    }
}

if (!function_exists('jsonErrorResponse')) {
    function jsonErrorResponse(int $status, string $message): void
    {
        http_response_code($status);
        echo json_encode([
            'estado' => 'error',
            'mensaje' => $message,
        ], JSON_UNESCAPED_UNICODE);
        if (shouldExit()) {
            exit;
        }
    }
}

if (stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html') !== false) {
    header('Location: /repo-marvel');
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

$path = trim((string) ($_GET['path'] ?? ''), '/');

try {
    $clientFactory = $GLOBALS['__github_client_factory'] ?? null;
    $client = is_callable($clientFactory) ? $clientFactory() : new GithubClient($rootPath);
    $response = $client->listRepositoryContents($path);

    if (!$response['ok']) {
        $message = '';
        if (is_array($response['decoded']) && isset($response['decoded']['message'])) {
            $message = (string) $response['decoded']['message'];
        } elseif (!empty($response['error'])) {
            $message = (string) $response['error'];
        }

        jsonErrorResponse(502, $message ?: 'No se pudo obtener el contenido del repositorio.');
        return;
    }

    $decoded = $response['decoded'];
    if (!is_array($decoded)) {
        jsonErrorResponse(502, 'La respuesta de GitHub no tiene el formato esperado.');
        return;
    }

    $items = [];
    foreach ($decoded as $node) {
        if (!is_array($node)) {
            continue;
        }

        $type = (string) ($node['type'] ?? 'file');
        if (!in_array($type, ['dir', 'file'], true)) {
            continue;
        }

        $items[] = [
            'name' => (string) ($node['name'] ?? ''),
            'path' => (string) ($node['path'] ?? ''),
            'type' => $type,
            'size' => isset($node['size']) ? (int) $node['size'] : 0,
            'html_url' => (string) ($node['html_url'] ?? ''),
        ];
    }

    usort($items, static function (array $a, array $b): int {
        if ($a['type'] === $b['type']) {
            return strcasecmp($a['name'], $b['name']);
        }

        return $a['type'] === 'dir' ? -1 : 1;
    });

    jsonResponse(200, [
        'estado' => 'exito',
        'path' => $path,
        'items' => $items,
    ]);
} catch (\Throwable $e) {
    jsonErrorResponse(500, $e->getMessage());
}

if (!function_exists('jsonResponse')) {
    function jsonResponse(int $status, array $payload): void
    {
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        if (shouldExit()) {
            exit;
        }
    }
}

if (!function_exists('jsonErrorResponse')) {
    function jsonErrorResponse(int $status, string $message): void
    {
        http_response_code($status);
        echo json_encode([
            'estado' => 'error',
            'mensaje' => $message,
        ], JSON_UNESCAPED_UNICODE);
        if (shouldExit()) {
            exit;
        }
    }
}

if (!function_exists('shouldExit')) {
    function shouldExit(): bool
    {
        return !defined('GITHUB_REPO_BROWSER_TEST') || GITHUB_REPO_BROWSER_TEST !== true;
    }
}
