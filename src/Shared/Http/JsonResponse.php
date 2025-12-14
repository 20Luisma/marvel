<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Metrics\PrometheusMetrics;

final class JsonResponse
{
    private const STATUS_SUCCESS = 'éxito';
    private const STATUS_ERROR = 'error';
    /** @var array<string, mixed>|null */
    private static ?array $lastPayload = null;

    /**
     * @return array<string, mixed>
     */
    public static function success(mixed $data = [], int $statusCode = 200): array
    {
        return self::send(self::STATUS_SUCCESS, $data, null, $statusCode);
    }

    /**
     * @return array<string, mixed>
     */
    public static function error(string $message, int $statusCode = 400): array
    {
        return self::send(self::STATUS_ERROR, null, $message, $statusCode);
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    private static function send(string $status, mixed $data, ?string $message, int $statusCode, array $headers = []): array
    {
        $payload = ['estado' => $status];

        if ($status === self::STATUS_SUCCESS) {
            $payload['datos'] = $data ?? [];
        }

        if ($message !== null) {
            $payload['message'] = $message;
        }

        self::$lastPayload = $payload;

        // No interrumpimos la ejecución en CLI (PHPUnit) para permitir que los tests continúen.
        if (PHP_SAPI === 'cli') {
            return $payload;
        }

        if ($statusCode >= 500) {
            PrometheusMetrics::incrementErrors();
        }

        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache');

        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Expose last payload for test assertions in CLI context.
     *
     * @return array<string, mixed>|null
     */
    public static function lastPayload(): ?array
    {
        return self::$lastPayload;
    }

    /**
     * Reset last payload for test isolation.
     */
    public static function resetLastPayload(): void
    {
        self::$lastPayload = null;
    }
}
