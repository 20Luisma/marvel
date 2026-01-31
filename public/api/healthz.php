<?php
/**
 * Liveness and Readiness Probe for Kubernetes/Cloud
 * -----------------------------------------------
 * Este endpoint es utilizado por el orquestador para verificar que el servicio
 * está vivo y listo para recibir tráfico.
 */

header('Content-Type: application/json');

$checks = [
    'storage' => is_writable(__DIR__ . '/../../storage') ? 'healthy' : 'degraded',
    'config' => file_exists(__DIR__ . '/../../.env') ? 'healthy' : 'error'
];

$isHealthy = !in_array('error', $checks);

if (!$isHealthy) {
    http_response_code(503);
} else {
    http_response_code(200);
}

echo json_encode([
    'status' => $isHealthy ? 'ok' : 'error',
    'service' => 'clean-marvel-app',
    'timestamp' => time(),
    'version' => '1.0.0',
    'checks' => $checks
], JSON_PRETTY_PRINT);
