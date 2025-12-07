<?php

declare(strict_types=1);

namespace App\Services;

interface SonarMetricsClient
{
    /**
     * @return array<string, mixed>
     */
    public function fetchMetrics(): array;
}
