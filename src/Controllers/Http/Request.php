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
        $raw = self::$rawInputOverride
            ?? ($GLOBALS['__raw_input__'] ?? file_get_contents('php://input'));

        self::$rawInputOverride = null;

        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            JsonResponse::error('JSON inválido', 400);
            exit;
        }

        return $decoded;
    }

    public static function wantsHtml(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';

        return stripos($accept, 'text/html') !== false;
    }
}
