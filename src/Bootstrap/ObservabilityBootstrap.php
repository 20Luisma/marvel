<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Monitoring\TokenMetricsService;
use Sentry\ClientBuilder;
use Sentry\SentrySdk;
use Sentry\State\Hub;
use Throwable;

final class ObservabilityBootstrap
{
    /**
     * @return array<string, mixed>
     */
    public static function initialize(string $appEnvironment, string $traceId): array
    {
        $sentryDsn = $_ENV['SENTRY_DSN'] ?? getenv('SENTRY_DSN') ?: null;
        $resolvedEnv = $appEnvironment;

        if ($resolvedEnv === '') {
            $resolvedEnv = 'local';
        }

        $sentryHub = null;

        if ($sentryDsn) {
            try {
                $client = ClientBuilder::create([
                    'dsn' => $sentryDsn,
                    'environment' => $resolvedEnv,
                    'traces_sample_rate' => 0.2,
                ])->getClient();

                $sentryHub = new Hub($client);
                SentrySdk::setCurrentHub($sentryHub);

                set_error_handler(static function ($severity, $message, $file, $fileLine) {
                    if (!(error_reporting() & $severity)) {
                        return false;
                    }

                    \Sentry\captureMessage(sprintf('%s in %s:%d', $message, $file, $fileLine));
                    return false;
                });

                set_exception_handler(static function (\Throwable $exception): void {
                    \Sentry\captureException($exception);
                    throw $exception;
                });
            } catch (Throwable $e) {
                error_log('Error inicializando Sentry: ' . $e->getMessage());
            }
        }

        return [
            'monitoring' => [
                'tokens' => new TokenMetricsService(),
                'trace_id' => $traceId,
                'sentry' => $sentryHub,
            ],
        ];
    }
}
