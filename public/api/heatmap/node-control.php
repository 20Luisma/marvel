<?php
declare(strict_types=1);

/**
 * API: Control de nodos Multi-Cloud (simulación para demo TFM)
 *
 * POST /api/heatmap/node-control.php
 * Body: { "node": "gcp"|"aws", "action": "disable"|"enable" }
 *
 * GET /api/heatmap/node-control.php
 * Devuelve el estado actual de ambos nodos + cola pendiente.
 *
 * IMPORTANTE: No apaga servidores reales. Solo activa un flag en
 * storage/heatmap/node_status.json que el cliente PHP lee para
 * simular la caída a nivel de código.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Requiere autenticación (solo admin puede controlar nodos)
require_once dirname(__DIR__, 3) . '/src/Bootstrap/AppBootstrap.php';
use App\Infrastructure\Http\AuthGuards;
AuthGuards::requireAuth();
AuthGuards::requireAdmin();

$storageDir  = dirname(__DIR__, 3) . '/storage/heatmap';
$statusFile  = $storageDir . '/node_status.json';
$queueFile   = $storageDir . '/pending_clicks.json';

if (!is_dir($storageDir)) {
    mkdir($storageDir, 0755, true);
}

// ─── Estado por defecto ───────────────────────────────────────────────────────
function loadStatus(string $file): array
{
    if (!is_file($file)) {
        return ['gcp' => 'online', 'aws' => 'online', 'updated_at' => time()];
    }
    $raw = file_get_contents($file);
    $decoded = json_decode($raw ?: '', true);
    return is_array($decoded) ? $decoded : ['gcp' => 'online', 'aws' => 'online', 'updated_at' => time()];
}

function saveStatus(string $file, array $status): void
{
    $status['updated_at'] = time();
    file_put_contents($file, json_encode($status, JSON_PRETTY_PRINT), LOCK_EX);
}

function queueCount(string $queueFile): int
{
    if (!is_file($queueFile)) return 0;
    $raw = file_get_contents($queueFile);
    $decoded = json_decode($raw ?: '', true);
    return is_array($decoded) ? count($decoded) : 0;
}

// ─── GET: devuelve estado actual ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $status = loadStatus($statusFile);
    echo json_encode([
        'nodes' => [
            'gcp' => [
                'status'   => $status['gcp'] ?? 'online',
                'label'    => 'GCP · South Carolina',
                'ip'       => '34.74.102.123',
                'flag'     => '🔵',
            ],
            'aws' => [
                'status'   => $status['aws'] ?? 'online',
                'label'    => 'AWS · París',
                'ip'       => '35.181.60.162',
                'flag'     => '🟠',
            ],
        ],
        'queue_count' => queueCount($queueFile),
        'updated_at'  => $status['updated_at'] ?? time(),
    ]);
    exit;
}

// ─── POST: cambia estado de un nodo ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body   = json_decode(file_get_contents('php://input') ?: '', true);
    $node   = (string) ($body['node']   ?? '');
    $action = (string) ($body['action'] ?? '');

    if (!in_array($node, ['gcp', 'aws'], true) || !in_array($action, ['enable', 'disable'], true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Parámetros inválidos. node: gcp|aws, action: enable|disable']);
        exit;
    }

    $status = loadStatus($statusFile);
    $status[$node] = ($action === 'disable') ? 'offline' : 'online';
    saveStatus($statusFile, $status);

    echo json_encode([
        'ok'     => true,
        'node'   => $node,
        'status' => $status[$node],
        'message' => $action === 'disable'
            ? "Nodo {$node} marcado como OFFLINE. Los clicks se encolarán."
            : "Nodo {$node} marcado como ONLINE. La cola se sincronizará.",
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
