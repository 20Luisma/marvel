<?php

declare(strict_types=1);

/**
 * NOTA DE SEGURIDAD (TRANSPARENCIA):
 * Este endpoint de métricas de seguridad es público para demostrar la capacidad
 * de integración de escaneos automatizados en la demo.
 * 
 * En un entorno real, estos resultados (que pueden contener información sensible
 * sobre la infraestructura) estarían protegidos tras un panel de administración.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\Security\SecurityScanService;

header('Content-Type: application/json');

// Crear servicio (auto-detecta URL según entorno)
$service = new SecurityScanService();

// Verificar si se fuerza re-escaneo
$forceRescan = isset($_GET['force']) && $_GET['force'] === '1';

// Si no se fuerza y el cache es fresco, usar cache
if (!$forceRescan && $service->isCacheFresh()) {
    $cache = $service->loadCache();
    if ($cache !== null) {
        $cache['fromCache'] = true;
        echo json_encode($cache, JSON_PRETTY_PRINT);
        exit;
    }
}

// Ejecutar escaneo
try {
    $results = $service->scan();
    
    // Guardar en cache
    $service->saveCache($results);
    
    echo json_encode($results, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error al ejecutar escaneo',
        'message' => $e->getMessage(),
        'fromCache' => false
    ], JSON_PRETTY_PRINT);
}
