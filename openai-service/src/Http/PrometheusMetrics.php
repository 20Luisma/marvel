<?php

declare(strict_types=1);

namespace Creawebes\OpenAI\Http;

final class PrometheusMetrics
{
    private static int $requests = 0;
    private static int $errors = 0;

    public static function incrementRequests(): void
    {
        self::$requests++;
    }

    public static function incrementErrors(): void
    {
        self::$errors++;
    }

    public static function respond(string $serviceName, ?string $version = null): void
    {
        header('Content-Type: text/plain; version=0.0.4');
        http_response_code(200);
        echo self::render($serviceName, $version);
    }

    public static function render(string $serviceName, ?string $version = null): string
    {
        $normalizedService = self::escapeLabelValue($serviceName);
        $normalizedVersion = self::escapeLabelValue(self::resolveVersion($version));

        $uptimeSeconds = 0;
        if (defined('APP_START_TIME')) {
            $uptimeSeconds = max(0, time() - (int) APP_START_TIME);
        }

        $lines = [];
        $lines[] = '# TYPE app_info gauge';
        $lines[] = sprintf('app_info{service="%s",version="%s"} 1', $normalizedService, $normalizedVersion);
        $lines[] = '# TYPE app_requests_total counter';
        $lines[] = sprintf('app_requests_total{service="%s"} %d', $normalizedService, self::$requests);
        $lines[] = '# TYPE app_errors_total counter';
        $lines[] = sprintf('app_errors_total{service="%s"} %d', $normalizedService, self::$errors);
        $lines[] = '# TYPE app_uptime_seconds gauge';
        $lines[] = sprintf('app_uptime_seconds{service="%s"} %d', $normalizedService, $uptimeSeconds);

        return implode("\n", $lines) . "\n";
    }

    private static function resolveVersion(?string $version): string
    {
        if (is_string($version) && trim($version) !== '') {
            return trim($version);
        }

        $fromEnv = $_ENV['APP_VERSION'] ?? getenv('APP_VERSION') ?: null;
        if (is_string($fromEnv) && trim($fromEnv) !== '') {
            return trim($fromEnv);
        }

        return 'dev';
    }

    private static function escapeLabelValue(string $value): string
    {
        return str_replace(
            ["\\", "\"", "\n"],
            ["\\\\", "\\\"", "\\n"],
            $value
        );
    }
}

