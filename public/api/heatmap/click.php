<?php
declare(strict_types=1);

if (!isPostRequest()) {
    respondJson(['status' => 'error', 'message' => 'Solo se permiten POST.'], 405);
}

if (!acceptsApplicationJson()) {
    respondJson(['status' => 'error', 'message' => 'Se requiere el encabezado Accept: application/json.'], 406);
}

$payload = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($payload)) {
    respondJson(['status' => 'error', 'message' => 'JSON inválido o body vacío.'], 400);
}

$page = normalizePage($payload['page'] ?? '');
$x = normalizeCoordinate($payload['x'] ?? null);
$y = normalizeCoordinate($payload['y'] ?? null);
$viewportW = normalizePositiveNumber($payload['viewportW'] ?? null);
$viewportH = normalizePositiveNumber($payload['viewportH'] ?? null);
$timestamp = normalizeTimestamp($payload['timestamp'] ?? null);

$event = [
    'page' => $page,
    'x' => $x,
    'y' => $y,
    'viewportW' => $viewportW,
    'viewportH' => $viewportH,
    'timestamp' => $timestamp,
];

require_once __DIR__ . '/HeatmapLogCleaner.php';

$storagePath = dirname(__DIR__, 2) . '/storage/heatmap';
try {
    $cleaner = new HeatmapLogCleaner($storagePath);
    $cleaner->prepare();
} catch (RuntimeException $exception) {
    respondJson(['status' => 'error', 'message' => $exception->getMessage()], 500);
}

$logFile = $cleaner->monthlyLogPath(time());
$pagesFile = $storagePath . '/pages.json';

file_put_contents($logFile, json_encode($event, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
updatePagesList($pagesFile, $page);

try {
    $cleaner->maybeCleanup(time());
} catch (RuntimeException $exception) {
    error_log('Heatmap cleanup failed: ' . $exception->getMessage());
}
updatePagesList($pagesFile, $page);

respondJson(['status' => 'ok']);

function isPostRequest(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

function acceptsApplicationJson(): bool
{
    $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
    return stripos($acceptHeader, 'application/json') !== false;
}

function normalizePage(string $page): string
{
    $clean = trim($page);
    return $clean === '' ? '/' : $clean;
}

function normalizeCoordinate(mixed $value): float
{
    if (!is_numeric($value)) {
        respondJson(['status' => 'error', 'message' => 'Coordenadas inválidas.'], 400);
    }

    $float = (float) $value;
    return max(0.0, min(1.0, $float));
}

function normalizePositiveNumber(mixed $value): float
{
    if (!is_numeric($value)) {
        respondJson(['status' => 'error', 'message' => 'Tamaño de viewport inválido.'], 400);
    }

    $float = (float) $value;
    return max(1.0, $float);
}

function normalizeTimestamp(mixed $value): int
{
    if ($value === null) {
        return time();
    }

    if (is_numeric($value)) {
        return (int) $value;
    }

    if (is_string($value)) {
        $time = strtotime($value);
        if ($time !== false) {
            return $time;
        }
    }

    return time();
}

function updatePagesList(string $pagesFile, string $page): void
{
    $pages = [];
    if (is_file($pagesFile)) {
        $json = json_decode(file_get_contents($pagesFile) ?: '[]', true);
        if (is_array($json)) {
            $pages = $json;
        }
    }

    if (!in_array($page, $pages, true)) {
        $pages[] = $page;
        file_put_contents($pagesFile, json_encode(array_values($pages), JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
}

function respondJson(array $payload, int $statusCode = 200): never
{
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
