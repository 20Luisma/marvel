<?php
declare(strict_types=1);

namespace App\Heatmap\Infrastructure;

final class HttpHeatmapApiClient implements HeatmapApiClient
{
    private const DEFAULT_TIMEOUT = 10;

    private string $baseUrl;
    private ?string $apiToken;

    public function __construct(string $baseUrl, ?string $apiToken = null)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiToken = $apiToken !== null && $apiToken !== '' ? $apiToken : null;
    }

    public function sendClick(array $payload): array
    {
        return $this->request('POST', '/track', $this->mapClickPayload($payload));
    }

    public function getSummary(array $query): array
    {
        return $this->request('GET', '/events', null, $query);
    }

    public function getPages(): array
    {
        return $this->request('GET', '/events');
    }

    /**
     * @param array<string,mixed>|null $payload
     * @param array<string,string> $query
     * @return array{statusCode:int,body:string}
     */
    private function request(string $method, string $path, ?array $payload = null, array $query = []): array
    {
        $url = $this->baseUrl . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return [
                'statusCode' => 502,
                'body' => json_encode([
                    'status' => 'error',
                    'message' => 'Heatmap microservice unavailable',
                    'detail' => 'cURL init failed',
                ], JSON_UNESCAPED_UNICODE),
            ];
        }

        $headers = ['Accept: application/json'];
        if ($this->apiToken !== null) {
            $headers[] = 'X-API-Token: ' . $this->apiToken;
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => self::DEFAULT_TIMEOUT,
        ];

        if ($payload !== null) {
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                return [
                    'statusCode' => 400,
                    'body' => json_encode([
                        'status' => 'error',
                        'message' => 'Invalid payload for heatmap request',
                    ], JSON_UNESCAPED_UNICODE),
                ];
            }
            $options[CURLOPT_POSTFIELDS] = $json;
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_HTTPHEADER] = $headers;
        }

        curl_setopt_array($ch, $options);
        $responseBody = curl_exec($ch);

        if ($responseBody === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return [
                'statusCode' => 502,
                'body' => json_encode([
                    'status' => 'error',
                    'message' => 'Heatmap microservice unavailable',
                    'detail' => $error,
                ], JSON_UNESCAPED_UNICODE),
            ];
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return [
            'statusCode' => $statusCode > 0 ? $statusCode : 502,
            'body' => $responseBody,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function mapClickPayload(array $payload): array
    {
        $mapped = [
            'page_url' => (string) ($payload['page'] ?? $payload['page_url'] ?? '/'),
            'x' => (float) ($payload['x'] ?? 0),
            'y' => (float) ($payload['y'] ?? 0),
            'viewport_width' => (int) ($payload['viewportW'] ?? $payload['viewport_width'] ?? 0),
            'viewport_height' => (int) ($payload['viewportH'] ?? $payload['viewport_height'] ?? 0),
            'scroll_y' => (int) ($payload['scrollY'] ?? $payload['scroll_y'] ?? 0),
        ];

        if (isset($payload['scrollHeight'])) {
            $mapped['scroll_height'] = (int) $payload['scrollHeight'];
        }

        if (isset($payload['timestamp'])) {
            $mapped['timestamp'] = $payload['timestamp'];
        }

        return $mapped;
    }
}
