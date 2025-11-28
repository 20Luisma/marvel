<?php

declare(strict_types=1);

namespace Src\Controllers\Http;

use App\Shared\Http\JsonResponse;

final class Request
{
    /**
     * @return array<string,mixed>
     */
    public static function jsonBody(): array
    {
        if (defined('PHPUNIT_RUNNING') && isset($GLOBALS['mock_php_input'])) {
            $raw = $GLOBALS['mock_php_input'];
        } elseif (isset($GLOBALS['__raw_input__'])) {
            $raw = $GLOBALS['__raw_input__'];
        } else {
            $raw = file_get_contents('php://input');
        }

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
