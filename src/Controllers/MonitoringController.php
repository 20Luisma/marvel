<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Shared\Http\JsonResponse;
use App\Shared\Infrastructure\Http\HttpClientInterface;
use App\Services\GithubClient;

final class MonitoringController
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $rootPath
    ) {
    }

    public function accessibility(): void
    {
        $waveKey = trim((string) (getenv('WAVE_API_KEY') ?: ($_ENV['WAVE_API_KEY'] ?? '')));
        if ($waveKey === '') {
            JsonResponse::error('Falta configurar WAVE_API_KEY', 500);
            return;
        }

        $rawBody = $_SERVER['MARVEL_RAW_BODY'] ?? file_get_contents('php://input');
        $body = json_decode((string)$rawBody, true);
        
        $urls = $body['urls'] ?? [];
        if (!is_array($urls) || count($urls) === 0) {
            JsonResponse::error('No URLs provided', 400);
            return;
        }

        $results = [];
        foreach ($urls as $url) {
            $results[] = $this->analyzeWave((string)$url, $waveKey);
        }

        echo json_encode(['estado' => 'exito', 'paginas' => $results], JSON_UNESCAPED_UNICODE);
    }

    public function performance(): void
    {
        $psiKey = trim((string) (getenv('PSI_API_KEY') ?: ($_ENV['PSI_API_KEY'] ?? '')));
        echo json_encode([
            'estado' => 'exito', 
            'active' => $psiKey !== '',
            'message' => 'Performance metrics centralized.'
        ], JSON_UNESCAPED_UNICODE);
    }

    public function github(): void
    {
        $from = $_GET['from'] ?? date('Y-m-d', strtotime('-14 days'));
        $to   = $_GET['to'] ?? date('Y-m-d');

        $client = new GithubClient($this->rootPath, $this->httpClient);
        $json = $client->fetchActivity((string)$from, (string)$to);

        if (isset($json['status']) && is_int($json['status'])) {
            http_response_code($json['status']);
        }

        echo json_encode($json, JSON_UNESCAPED_UNICODE);
    }

    private function analyzeWave(string $url, string $key): array
    {
        $endpoint = 'https://wave.webaim.org/api/request';
        $payload = http_build_query([
            'key' => $key,
            'url' => $url,
            'format' => 'json',
            'reporttype' => '2',
        ]);

        try {
            // Usamos post() genérico para application/x-www-form-urlencoded
            $response = $this->httpClient->post($endpoint, $payload, [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'User-Agent' => 'CleanMarvel/AccessBot'
            ]);

            if ($response->statusCode !== 200) {
                return ['estado' => 'error', 'url' => $url, 'mensaje' => 'WAVE error ' . $response->statusCode];
            }

            $decoded = json_decode($response->body, true);
            if (!is_array($decoded)) {
                return ['estado' => 'error', 'url' => $url, 'mensaje' => 'Invalid WAVE response'];
            }

            $statistics = $decoded['statistics'] ?? [];
            $categories = $decoded['categories'] ?? [];

            return [
                'estado' => 'exito',
                'url' => $url,
                'titulo' => (string) ($statistics['pagetitle'] ?? ''),
                'errores' => (int) ($categories['error']['count'] ?? 0),
                'contraste' => (int) ($categories['contrast']['count'] ?? 0),
                'alertas' => (int) ($categories['alert']['count'] ?? 0),
            ];
        } catch (\Throwable $e) {
            return ['estado' => 'error', 'url' => $url, 'mensaje' => $e->getMessage()];
        }
    }
}
