<?php
declare(strict_types=1);

if (!isGetRequest()) {
    respondJson(['status' => 'error', 'message' => 'Solo se permiten GET.'], 405);
}

if (!acceptsApplicationJson()) {
    respondJson(['status' => 'error', 'message' => 'Se requiere el encabezado Accept: application/json.'], 406);
}

$storagePath = dirname(__DIR__, 2) . '/storage/heatmap';
$pagesFile = $storagePath . '/pages.json';
$pages = [];

if (is_file($pagesFile)) {
    $decoded = json_decode(file_get_contents($pagesFile) ?: '[]', true);
    if (is_array($decoded)) {
        $pages = $decoded;
    }
}

if ($pages === [] && is_file($storagePath . '/clicks.log')) {
    $pages = scanPagesFromLog($storagePath . '/clicks.log');
}

$pages = array_values(array_unique($pages));
sort($pages);

respondJson(['status' => 'ok', 'pages' => $pages]);

function isGetRequest(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET';
}

function acceptsApplicationJson(): bool
{
    $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
    return stripos($acceptHeader, 'application/json') !== false;
}

function scanPagesFromLog(string $logFile): array
{
    $pages = [];
    $file = new SplFileObject($logFile, 'r');
    while (!$file->eof()) {
        $line = trim((string) $file->fgets());
        if ($line === '') {
            continue;
        }

        $event = json_decode($line, true);
        if (!is_array($event)) {
            continue;
        }

        $page = trim((string) ($event['page'] ?? '/'));
        if ($page === '') {
            $page = '/';
        }

        $pages[] = $page;
    }

    return array_values(array_unique($pages));
}

function respondJson(array $payload, int $statusCode = 200): never
{
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
