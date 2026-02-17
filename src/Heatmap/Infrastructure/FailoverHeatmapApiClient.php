<?php
declare(strict_types=1);

namespace App\Heatmap\Infrastructure;

final class FailoverHeatmapApiClient implements HeatmapApiClient
{
    /** @var HeatmapApiClient[] */
    private array $clients;

    public function __construct(HeatmapApiClient ...$clients)
    {
        $this->clients = $clients;
    }

    public function sendClick(array $payload): array
    {
        return $this->attempt('sendClick', [$payload]);
    }

    public function getSummary(array $query): array
    {
        return $this->attempt('getSummary', [$query]);
    }

    public function getPages(): array
    {
        return $this->attempt('getPages', []);
    }

    /**
     * @param string $method
     * @param array<int, mixed> $arguments
     * @return array{statusCode:int, body:string}
     */
    private function attempt(string $method, array $arguments): array
    {
        $lastResult = [
            'statusCode' => 503,
            'body' => (string) json_encode(['status' => 'error', 'message' => 'No heatmap clients available'])
        ];

        foreach ($this->clients as $client) {
            try {
                $result = $client->$method(...$arguments);
                if ($result['statusCode'] >= 200 && $result['statusCode'] < 300) {
                    return $result;
                }
                $lastResult = $result;
            } catch (\Throwable $e) {
                $lastResult = [
                    'statusCode' => 502,
                    'body' => (string) json_encode(['status' => 'error', 'message' => 'Client failed', 'detail' => $e->getMessage()])
                ];
            }
        }

        return $lastResult;
    }
}
