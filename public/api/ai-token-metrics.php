<?php

declare(strict_types=1);

use App\Monitoring\TokenMetricsService;

require_once __DIR__ . '/../../vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Convert PHP errors to exceptions so we always return JSON (hosting silences fatals)
set_error_handler(static function ($severity, $message, $file, $line): bool {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Verificar acceso (solo admin); evitar redirects silenciosos en CLI/fetch
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$role = $_SESSION['user_role'] ?? ($_SESSION['auth']['role'] ?? null);
if (PHP_SAPI !== 'cli' && ($role !== 'admin')) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'error' => 'Acceso denegado (solo admin)',
    ]);
    exit;
}

try {
    $service = new TokenMetricsService();
    $metrics = $service->getMetrics();

    echo json_encode([
        'ok'           => true,
        'global'       => $metrics['global']       ?? [],
        'by_model'     => $metrics['by_model']     ?? [],
        'by_feature'   => $metrics['by_feature']   ?? [],
        'recent_calls' => $metrics['recent_calls'] ?? [],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'Error al obtener métricas: ' . $e->getMessage(),
        'trace' => $e->getFile() . ':' . $e->getLine(),
    ]);
}
