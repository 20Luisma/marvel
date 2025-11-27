<?php

declare(strict_types=1);

use App\Config\ServiceUrlProvider;
use App\Shared\Infrastructure\Http\HttpClientInterface;
use Src\Controllers\RagProxyController;

require_once dirname(__DIR__, 4) . '/vendor/autoload.php';

$container = require dirname(__DIR__, 4) . '/src/bootstrap.php';

$http = $container['http']['client'] ?? null;
if (!$http instanceof HttpClientInterface) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Cliente HTTP no disponible.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$ragUrl = resolve_rag_url($container);
$token = $container['security']['internal_api_key'] ?? null;
$token = is_string($token) ? $token : null;

$controller = new RagProxyController($http, $ragUrl, $token);
$controller->forwardHeroesComparison();

function resolve_rag_url(array $container): string
{
    $configured = $_ENV['RAG_SERVICE_URL'] ?? getenv('RAG_SERVICE_URL') ?: null;
    if (is_string($configured) && trim($configured) !== '') {
        $base = rtrim($configured, '/');
        if (str_contains($base, '/rag/agent')) {
            $base = str_replace('/rag/agent', '/rag/heroes', $base);
        } elseif (!str_contains($base, '/rag/heroes')) {
            $base .= '/rag/heroes';
        }
        return $base;
    }

    $provider = $container['services']['urlProvider'] ?? null;
    if ($provider instanceof ServiceUrlProvider) {
        return $provider->getRagHeroesUrl();
    }

    return 'http://localhost:8082/rag/heroes';
}
