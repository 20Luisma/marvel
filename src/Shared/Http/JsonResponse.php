<?php

declare(strict_types=1);

namespace App\Shared\Http;

final class JsonResponse
{
    private const STATUS_SUCCESS = 'éxito';
    private const STATUS_ERROR = 'error';

    public static function success(mixed $data = [], int $statusCode = 200): void
    {
        self::send(self::STATUS_SUCCESS, $data, null, $statusCode);
    }

    public static function error(string $message, int $statusCode = 400): void
    {
        self::send(self::STATUS_ERROR, null, $message, $statusCode);
    }

    /**
     * @param array<string, string> $headers
     */
    private static function send(string $status, mixed $data, ?string $message, int $statusCode, array $headers = []): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        header('Cache-Control: no-cache');

        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }

        $payload = ['estado' => $status];

        if ($status === self::STATUS_SUCCESS) {
            $payload['datos'] = $data ?? [];
        }

        if ($message !== null) {
            $payload['message'] = $message;
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
