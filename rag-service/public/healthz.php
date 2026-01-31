<?php
/**
 * Health Check para RAG Service
 */

header('Content-Type: application/json');

$checks = [
    'env' => file_exists(__DIR__ . '/../.env') ? 'healthy' : 'error',
    // Check de carga de embeddings si fuera crítico
];

$isHealthy = !in_array('error', $checks);

if (!$isHealthy) {
    http_response_code(503);
} else {
    http_response_code(200);
}

echo json_encode([
    'status' => $isHealthy ? 'ok' : 'error',
    'service' => 'rag-service',
    'timestamp' => time(),
    'checks' => $checks
], JSON_PRETTY_PRINT);
