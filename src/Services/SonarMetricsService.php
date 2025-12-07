<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

final class SonarMetricsService
{
    private SonarMetricsClient $client;

    public function __construct(SonarMetricsClient $client)
    {
        $this->client = $client;
    }

    /**
     * @return array{
     *     project_key: string,
     *     project_name: string,
     *     metrics: array{
     *         coverage: float,
     *         code_smells: int,
     *         bugs: int,
     *         vulnerabilities: int
     *     }
     * }
     */
    public function getMetrics(): array
    {
        $payload = $this->client->fetchMetrics();
        $metrics = $payload['metrics'] ?? [];

        return [
            'project_key' => (string)($payload['project_key'] ?? ''),
            'project_name' => (string)($payload['project_name'] ?? ''),
            'metrics' => [
                'coverage' => (float)($metrics['coverage']['value'] ?? 0.0),
                'code_smells' => (int)($metrics['code_smells']['value'] ?? 0),
                'bugs' => (int)($metrics['bugs']['value'] ?? 0),
                'vulnerabilities' => (int)($metrics['vulnerabilities']['value'] ?? 0)
            ]
        ];
    }
}
