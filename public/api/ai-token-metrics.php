<?php

declare(strict_types=1);

use App\Infrastructure\Http\AuthGuards;
use App\Monitoring\TokenMetricsService;

require_once __DIR__ . '/../../vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar acceso (solo admin)
try {
    AuthGuards::requireAuth();
    AuthGuards::requireAdmin();
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Acceso denegado']);
    exit;
}

try {
    $service = new TokenMetricsService();
    $metrics = $service->getMetrics();
    
    echo json_encode($metrics, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Error al obtener métricas: ' . $e->getMessage()
    ]);
}
