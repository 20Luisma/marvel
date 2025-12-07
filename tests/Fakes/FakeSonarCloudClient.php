<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Services\SonarMetricsClient;

final class FakeSonarCloudClient implements SonarMetricsClient
{
    public function fetchMetrics(): array
    {
        return [
            'project_key' => '20Luisma_marvel',
            'project_name' => 'marvel',
            'metrics' => [
                'coverage' => ['value' => 29.3],
                'code_smells' => ['value' => 143],
                'bugs' => ['value' => 0],
                'vulnerabilities' => ['value' => 0]
            ]
        ];
    }
}
