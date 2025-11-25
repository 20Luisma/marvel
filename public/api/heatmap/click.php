<?php
declare(strict_types=1);

use App\Heatmap\Infrastructure\HeatmapApiClient;
use App\Heatmap\Infrastructure\HttpHeatmapApiClient;

$rootPath = dirname(__DIR__, 3);
$autoload = $rootPath . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

$container = require_once $rootPath . '/src/bootstrap.php';
$client = resolveHeatmapClient($container ?? null);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respondJson(['status' => 'error', 'message' => 'Method not allowed'], 405);
}

if (!acceptsApplicationJson()) {
    respondJson(['status' => 'error', 'message' => 'Se requiere el encabezado Accept: application/json.'], 406);
}

$payload = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($payload)) {
    respondJson(['status' => 'error', 'message' => 'El body debe ser JSON.'], 400);
}

try {
    $apiResponse = $client->sendClick($payload);
    $statusCode = (int) $apiResponse['statusCode'];

    if ($statusCode >= 200 && $statusCode < 300) {
        respondJson(['status' => 'ok'], 200);
    }

    $message = extractErrorMessage($apiResponse['body']);
    respondJson(['status' => 'error', 'message' => $message], $statusCode > 0 ? $statusCode : 502);
} catch (Throwable $exception) {
    respondJson(['status' => 'error', 'message' => 'Heatmap service unavailable'], 502);
}

function resolveHeatmapClient(?array $container): HeatmapApiClient
{
    $instance = $container['services']['heatmapApiClient'] ?? null;
    if ($instance instanceof HeatmapApiClient) {
        return $instance;
    }

    $baseUrl = trim((string) (getenv('HEATMAP_API_BASE_URL') ?: 'http://34.74.102.123:8080'));
    if ($baseUrl === '') {
        $baseUrl = 'http://34.74.102.123:8080';
    }
    $token = trim((string) (getenv('HEATMAP_API_TOKEN') ?: ''));

    return new HttpHeatmapApiClient($baseUrl, $token !== '' ? $token : null);
}

function acceptsApplicationJson(): bool
{
    $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
    return stripos($acceptHeader, 'application/json') !== false;
}

/**
 * @param array{statusCode:int,body:string} $apiResponse
 */
function relayApiResponse(array $apiResponse): never
{
    $statusCode = (int) $apiResponse['statusCode'];
    $body = (string) $apiResponse['body'];

    header('Content-Type: application/json; charset=utf-8');
    http_response_code($statusCode);
    echo $body;
    exit;
}

function respondJson(array $payload, int $statusCode = 200): never
{
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function extractErrorMessage(string $body): string
{
    $decoded = json_decode($body, true);
    if (is_array($decoded) && isset($decoded['message']) && is_string($decoded['message'])) {
        return $decoded['message'];
    }

    return 'Heatmap microservice error';
}
