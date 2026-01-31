<?php
/**
 * Health Check para OpenAI Service
 */

header('Content-Type: application/json');

$checks = [
    'env' => file_exists(__DIR__ . '/../.env') ? 'healthy' : 'error',
    // Podríamos añadir un check de conectividad con OpenAI aquí si fuera necesario
];

$isHealthy = !in_array('error', $checks);

if (!$isHealthy) {
    http_response_code(503);
} else {
    http_response_code(200);
}

echo json_encode([
    'status' => $isHealthy ? 'ok' : 'error',
    'service' => 'openai-service',
    'timestamp' => time(),
    'checks' => $checks
], JSON_PRETTY_PRINT);
