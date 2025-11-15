<?php

declare(strict_types=1);

namespace Tests\Sonar;

use App\Services\SonarMetricsService;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeSonarCloudClient;

final class SonarMetricsServiceTest extends TestCase
{
    public function test_service_processes_sonar_metrics_payload(): void
    {
        $service = new SonarMetricsService(new FakeSonarCloudClient());

        $metrics = $service->getMetrics();

        self::assertSame(29.3, $metrics['metrics']['coverage']);
        self::assertSame(143, $metrics['metrics']['code_smells']);
        self::assertSame(0, $metrics['metrics']['bugs']);
    }
}
