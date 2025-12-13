<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Shared\Http\JsonResponse;

final class HttpRequestHarness
{
    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $get
     * @param array<string, mixed> $post
     * @param callable(): void $dispatch
     * @return array{output: string, status: int, headers: list<string>, payload: array<string, mixed>|null}
     */
    public static function dispatch(callable $dispatch, array $server = [], array $get = [], array $post = []): array
    {
        self::resetGlobals();

        $_SERVER = array_merge($_SERVER, $server);
        $_GET = $get;
        $_POST = $post;

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        ob_start();
        $dispatch();
        $output = (string) ob_get_clean();

        $status = 200;
        if (array_key_exists('response_code', $GLOBALS) && is_int($GLOBALS['response_code'])) {
            $status = $GLOBALS['response_code'];
        } else {
            $fromGlobal = http_response_code();
            if (is_int($fromGlobal)) {
                $status = $fromGlobal;
            }
        }

        return [
            'output' => $output,
            'status' => $status,
            'headers' => headers_list(),
            'payload' => JsonResponse::lastPayload(),
        ];
    }

    public static function resetGlobals(): void
    {
        header_remove();
        http_response_code(200);
        JsonResponse::resetLastPayload();
        unset($GLOBALS['response_code'], $GLOBALS['headers']);

        $_GET = [];
        $_POST = [];
        $_SERVER = [];
        $_SESSION = [];
    }
}
