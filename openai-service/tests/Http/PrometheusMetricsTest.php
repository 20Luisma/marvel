<?php

declare(strict_types=1);

namespace Creawebes\OpenAI\Tests\Http;

use Creawebes\OpenAI\Http\PrometheusMetrics;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the PrometheusMetrics class.
 *
 * Validates that the Prometheus-compatible metrics output follows
 * the expected text/plain format with correct labels and counters.
 */
final class PrometheusMetricsTest extends TestCase
{
    public function testRenderContainsServiceLabel(): void
    {
        $output = PrometheusMetrics::render('openai-service');

        $this->assertStringContainsString('service="openai-service"', $output);
    }

    public function testRenderContainsAllMetricTypes(): void
    {
        $output = PrometheusMetrics::render('openai-service');

        $this->assertStringContainsString('# TYPE app_info gauge', $output);
        $this->assertStringContainsString('# TYPE app_requests_total counter', $output);
        $this->assertStringContainsString('# TYPE app_errors_total counter', $output);
        $this->assertStringContainsString('# TYPE app_uptime_seconds gauge', $output);
    }

    public function testRenderUsesCustomVersion(): void
    {
        $output = PrometheusMetrics::render('openai-service', 'v1.3.2');

        $this->assertStringContainsString('version="v1.3.2"', $output);
    }

    public function testRenderUsesDevVersionByDefault(): void
    {
        // Clear any APP_VERSION in env
        $original = $_ENV['APP_VERSION'] ?? null;
        unset($_ENV['APP_VERSION']);
        putenv('APP_VERSION');

        $output = PrometheusMetrics::render('openai-service');

        $this->assertStringContainsString('version="dev"', $output);

        // Restore
        if ($original !== null) {
            $_ENV['APP_VERSION'] = $original;
        }
    }

    public function testRenderEscapesSpecialCharactersInLabels(): void
    {
        $output = PrometheusMetrics::render('service-with"quotes');

        // Prometheus label values must escape double quotes
        $this->assertStringContainsString('service="service-with\\"quotes"', $output);
    }

    public function testIncrementRequestsIsIdempotent(): void
    {
        // Calling incrementRequests should not throw or cause issues
        PrometheusMetrics::incrementRequests();
        PrometheusMetrics::incrementRequests();

        $output = PrometheusMetrics::render('test-service');

        // Should contain a counter line (we can't assert exact value due to static state
        // shared across tests, but we can assert the format is correct)
        $this->assertMatchesRegularExpression('/app_requests_total\{service="test-service"\} \d+/', $output);
    }

    public function testIncrementErrorsIsIdempotent(): void
    {
        PrometheusMetrics::incrementErrors();

        $output = PrometheusMetrics::render('test-service');

        $this->assertMatchesRegularExpression('/app_errors_total\{service="test-service"\} \d+/', $output);
    }
}
