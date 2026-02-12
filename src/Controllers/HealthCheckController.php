<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\ServiceUrlProvider;
use App\Shared\Infrastructure\Http\HttpClientInterface;

final class HealthCheckController
{
    private const TIMEOUT_SECONDS = 5;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ServiceUrlProvider $serviceUrlProvider,
        private readonly string $environment
    ) {
    }

    public function check(): void
    {
        $traceId = $_SERVER['X_TRACE_ID'] ?? '-';
        $startTime = microtime(true);

        $services = [
            'app' => $this->checkApp(),
            'rag-service' => $this->checkService(
                $this->serviceUrlProvider->getRagBaseUrl($this->environment),
                'rag-service'
            ),
            'openai-service' => $this->checkService(
                $this->serviceUrlProvider->getOpenAiBaseUrl($this->environment),
                'openai-service'
            ),
        ];

        $allHealthy = true;
        foreach ($services as $service) {
            if ($service['status'] !== 'healthy') {
                $allHealthy = false;
                break;
            }
        }

        $totalMs = round((microtime(true) - $startTime) * 1000);

        $response = [
            'status' => $allHealthy ? 'healthy' : 'degraded',
            'timestamp' => date('c'),
            'trace_id' => is_string($traceId) ? $traceId : '-',
            'environment' => $this->environment,
            'response_time_ms' => $totalMs,
            'services' => $services,
        ];

        $statusCode = $allHealthy ? 200 : 503;
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    /**
     * @return array{status: string, response_time_ms: float}
     */
    private function checkApp(): array
    {
        $start = microtime(true);

        // Verificar que la app responde y el entorno está cargado
        $healthy = isset($_SERVER['X_TRACE_ID']) && session_status() !== PHP_SESSION_DISABLED;

        return [
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'response_time_ms' => round((microtime(true) - $start) * 1000, 1),
        ];
    }

    /**
     * @return array{status: string, response_time_ms: float, error?: string}
     */
    private function checkService(string $baseUrl, string $serviceName): array
    {
        $start = microtime(true);

        if ($baseUrl === '') {
            return [
                'status' => 'unknown',
                'response_time_ms' => 0,
                'error' => 'URL not configured',
            ];
        }

        $healthUrl = rtrim($baseUrl, '/') . '/health';

        try {
            $response = $this->httpClient->get(
                $healthUrl,
                [],
                timeoutSeconds: self::TIMEOUT_SECONDS,
                retries: 1
            );

            $ms = round((microtime(true) - $start) * 1000, 1);

            if ($response->statusCode >= 200 && $response->statusCode < 300) {
                $body = json_decode($response->body, true);
                $result = [
                    'status' => 'healthy',
                    'response_time_ms' => $ms,
                ];
                if (is_array($body) && isset($body['embeddings_enabled'])) {
                    $result['embeddings_enabled'] = $body['embeddings_enabled'];
                }
                return $result;
            }

            return [
                'status' => 'unhealthy',
                'response_time_ms' => $ms,
                'error' => "HTTP {$response->statusCode}",
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'unhealthy',
                'response_time_ms' => round((microtime(true) - $start) * 1000, 1),
                'error' => $e->getMessage(),
            ];
        }
    }
}
