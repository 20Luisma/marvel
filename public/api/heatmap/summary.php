<?php
declare(strict_types=1);

if (!isGetRequest()) {
    respondJson(['status' => 'error', 'message' => 'Solo se permiten GET.'], 405);
}

if (!acceptsApplicationJson()) {
    respondJson(['status' => 'error', 'message' => 'Se requiere el encabezado Accept: application/json.'], 406);
}

const GRID_ROWS = 20;
const GRID_COLS = 20;

$pageFilter = normalizePage(filter_input(INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
$grid = initializeGrid(GRID_ROWS, GRID_COLS);
$totalClicks = 0;
$storagePath = dirname(__DIR__, 2) . '/storage/heatmap';

require_once __DIR__ . '/HeatmapLogCleaner.php';
$cleaner = new HeatmapLogCleaner($storagePath);
$cleaner->prepare();
$logFiles = $cleaner->getLogFiles();

foreach ($logFiles as $logFile) {
    if (!is_file($logFile)) {
        continue;
    }

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

        $eventPage = normalizePage($event['page'] ?? '');
        if ($pageFilter !== '' && $eventPage !== $pageFilter) {
            continue;
        }

        $row = mapToCell($event['y'] ?? 0, GRID_ROWS);
        $col = mapToCell($event['x'] ?? 0, GRID_COLS);
        $grid[$row][$col]++;
        $totalClicks++;
    }
}

respondJson([
    'status' => 'ok',
    'rows' => GRID_ROWS,
    'cols' => GRID_COLS,
    'page' => $pageFilter !== '' ? $pageFilter : 'all',
    'totalClicks' => $totalClicks,
    'grid' => $grid,
]);

function isGetRequest(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET';
}

function acceptsApplicationJson(): bool
{
    $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
    return stripos($acceptHeader, 'application/json') !== false;
}

function initializeGrid(int $rows, int $cols): array
{
    return array_fill(0, $rows, array_fill(0, $cols, 0));
}

function normalizePage(mixed $value): string
{
    $clean = trim((string) $value);
    return $clean === '' ? '/' : $clean;
}

function mapToCell(mixed $value, int $segments): int
{
    $float = clampFloat((float) $value);
    $index = (int) floor($float * $segments);
    if ($index >= $segments) {
        $index = $segments - 1;
    }

    return max(0, $index);
}

function clampFloat(float $value): float
{
    if ($value < 0.0) {
        return 0.0;
    }
    if ($value > 0.9999999999) {
        return 0.9999999999;
    }

    return $value;
}

function respondJson(array $payload, int $statusCode = 200): never
{
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
