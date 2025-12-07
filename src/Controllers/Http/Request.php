<?php

declare(strict_types=1);

namespace Src\Controllers\Http;

use App\Shared\Http\JsonResponse;

final class Request
{
    private static ?string $rawInputOverride = null;

    public static function withJsonBody(string $json): void
    {
        self::$rawInputOverride = $json;
    }

    /**
     * @return array<string,mixed>
     */
    public static function jsonBody(): array
    {
        if (self::$rawInputOverride !== null) {
            $raw = self::$rawInputOverride;
            self::$rawInputOverride = null;
            $decoded = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                $response = JsonResponse::error('JSON inválido', 400);
                if (PHP_SAPI !== 'cli') {
                    exit;
                }
                return $response;
            }
            return $decoded;
        }

        try {
            return \Src\Http\RequestBodyReader::getJsonArray();
        } catch (\RuntimeException $e) {
            // Si el body está vacío, devolvemos array vacío para compatibilidad
            if ($e->getMessage() === 'El cuerpo de la petición está vacío') {
                return [];
            }
            // Si es JSON inválido, terminamos con error 400 como hacía antes
            $response = JsonResponse::error('JSON inválido', 400);
            if (PHP_SAPI !== 'cli') {
                exit;
            }
            return $response;
        }
    }

    public static function wantsHtml(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';

        return stripos($accept, 'text/html') !== false;
    }
}
