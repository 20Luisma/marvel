<?php
declare(strict_types=1);

// Cargar .env manualmente para que las variables estén disponibles
$rootPath = dirname(__DIR__, 3);
$envPath = $rootPath . '/.env';
if (is_file($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if (str_starts_with($line, '#')) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2) + [1 => '']);
        if ($key !== '') {
            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }
}

// Configuración de cabeceras para permitir CORS y JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Empezar sesión para verificar admin si es necesario
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificación básica de sesión
if (!isset($_SESSION['auth'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

// Determinar ruta según entorno
$appEnv = (string) (getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'local'));
$isHosting = (strpos($_SERVER['HTTP_HOST'] ?? '', 'contenido.creawebes.com') !== false || $appEnv === 'hosting');

$storageDir = $isHosting
    ? '/home/u968396048/domains/contenido.creawebes.com/public_html/iamasterbigschool/storage/heatmap'
    : dirname(__DIR__, 3) . '/storage/heatmap';

if (!is_dir($storageDir)) {
    @mkdir($storageDir, 0755, true);
}

$statusFile = $storageDir . '/node_status.json';
$queueFile  = $storageDir . '/pending_clicks.json';

// ─── Estructura base garantizada ──────────────────────────────────────────────
function getBaseStatus(): array
{
    return [
        'nodes' => [
            'gcp' => ['status' => 'online', 'label' => 'Google Cloud'],
            'aws' => ['status' => 'online', 'label' => 'Amazon Web Services']
        ],
        'updated_at' => time()
    ];
}

function loadStatus(string $file): array
{
    $status = getBaseStatus();
    if (is_file($file)) {
        $raw = file_get_contents($file);
        $decoded = json_decode($raw ?: '', true);
        if (is_array($decoded)) {
            // Migración/Merge: asegurar que 'nodes' existe y tiene claves
            if (isset($decoded['nodes']) && is_array($decoded['nodes'])) {
                foreach (['gcp', 'aws'] as $k) {
                    if (isset($decoded['nodes'][$k]['status'])) {
                        $status['nodes'][$k]['status'] = $decoded['nodes'][$k]['status'];
                    }
                }
            }
            // Soporte para formato antiguo si existiera
            foreach (['gcp', 'aws'] as $k) {
                if (isset($decoded[$k]) && is_string($decoded[$k])) {
                    $status['nodes'][$k]['status'] = $decoded[$k];
                }
            }
            if (isset($decoded['updated_at'])) {
                $status['updated_at'] = $decoded['updated_at'];
            }
        }
    }
    return $status;
}

function queueCount(string $queueFile): int
{
    if (!is_file($queueFile)) return 0;
    $raw = file_get_contents($queueFile);
    $decoded = json_decode($raw ?: '', true);
    return is_array($decoded) ? count($decoded) : 0;
}

// ─── Lógica de la API ─────────────────────────────────────────────────────────

$status = loadStatus($statusFile);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $status['queue_count'] = queueCount($queueFile);
    echo json_encode($status);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $node = $input['node'] ?? '';
    $action = $input['action'] ?? '';

    if (!in_array($node, ['gcp', 'aws']) || !in_array($action, ['enable', 'disable'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Parámetros inválidos']);
        exit;
    }

    $status['nodes'][$node]['status'] = ($action === 'enable') ? 'online' : 'offline';
    $status['updated_at'] = time();

    file_put_contents($statusFile, json_encode($status));

    // Si recuperamos un nodo, intentamos sincronizar la cola inmediatamente
    if ($action === 'enable') {
        try {
            $rootPath = dirname(__DIR__, 3);
            require_once $rootPath . '/vendor/autoload.php';
            
            // Crear cliente directamente en vez de depender del bootstrap
            $baseUrl   = trim((string) (getenv('HEATMAP_API_BASE_URL') ?: ($_ENV['HEATMAP_API_BASE_URL'] ?? '')));
            $secUrl    = trim((string) (getenv('HEATMAP_API_SECONDARY_URL') ?: ($_ENV['HEATMAP_API_SECONDARY_URL'] ?? '')));
            $token     = trim((string) (getenv('HEATMAP_API_TOKEN') ?: ($_ENV['HEATMAP_API_TOKEN'] ?? '')));
            
            $clients = [];
            if ($baseUrl !== '') {
                $clients[] = new \App\Heatmap\Infrastructure\HttpHeatmapApiClient($baseUrl, $token !== '' ? $token : null);
            }
            if ($secUrl !== '') {
                $clients[] = new \App\Heatmap\Infrastructure\HttpHeatmapApiClient($secUrl, $token !== '' ? $token : null);
            }
            
            if (!empty($clients)) {
                $replicatedClient = new \App\Heatmap\Infrastructure\ReplicatedHeatmapApiClient(...$clients);
                $replicatedClient->flushPendingQueue();
                error_log('[Heatmap Panel] Queue flush completed after enabling node: ' . $node);
            }
        } catch (\Throwable $e) {
            error_log('[Heatmap Panel] Queue flush failed: ' . $e->getMessage());
        }
    }

    echo json_encode([
        'status' => $status['nodes'][$node]['status'],
        'queue_count' => queueCount($queueFile),
        'nodes' => $status['nodes']
    ]);
    exit;
}
