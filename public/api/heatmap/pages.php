<?php
declare(strict_types=1);

use App\Heatmap\Infrastructure\HeatmapApiClient;
use App\Heatmap\Infrastructure\HttpHeatmapApiClient;

$rootPath = dirname(__DIR__, 3);
$autoload = $rootPath . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

$container = require $rootPath . '/src/bootstrap.php';
$client = resolveHeatmapClient($container ?? null);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    respondJson(['status' => 'error', 'message' => 'Method not allowed'], 405);
}

if (!acceptsApplicationJson()) {
    respondJson(['status' => 'error', 'message' => 'Se requiere el encabezado Accept: application/json.'], 406);
}

try {
    $apiResponse = $client->getPages();
    $statusCode = (int) $apiResponse['statusCode'];
    if ($statusCode < 200 || $statusCode >= 300) {
        $message = extractErrorMessage($apiResponse['body']);
        respondJson(['status' => 'error', 'message' => $message], $statusCode > 0 ? $statusCode : 502);
    }

    $body = json_decode($apiResponse['body'], true);
    if (!is_array($body)) {
        respondJson(['status' => 'error', 'message' => 'Respuesta inválida del microservicio'], 502);
    }

    $events = extractEvents($body);
    $pages = [];
    foreach ($events as $event) {
        if (!is_array($event)) {
            continue;
        }
        $pageUrl = isset($event['page_url']) ? (string) $event['page_url'] : (string) ($event['page'] ?? '');
        if ($pageUrl !== '') {
            $pages[$pageUrl] = true;
        }
    }

    respondJson(['status' => 'ok', 'pages' => array_keys($pages)], 200);
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

/**
 * @param array<string, mixed> $body
 * @return array<int, array<string, mixed>>
 */
function extractEvents(array $body): array
{
    if (isset($body['events']) && is_array($body['events'])) {
        return $body['events'];
    }

    return array_is_list($body) ? $body : [];
}

function extractErrorMessage(string $body): string
{
    $decoded = json_decode($body, true);
    if (is_array($decoded) && isset($decoded['message']) && is_string($decoded['message'])) {
        return $decoded['message'];
    }

    return 'Heatmap microservice error';
}
