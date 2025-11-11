<?php

declare(strict_types=1);

namespace Tests\Support;

final class OpenAITransportStub
{
    public static string $response = '';
    public static int $httpCode = 200;
    public static string $error = '';
    public static ?string $lastUrl = null;

    public static function reset(): void
    {
        self::$response = '';
        self::$httpCode = 200;
        self::$error = '';
        self::$lastUrl = null;
    }
}

namespace App\AI;

use Tests\Support\OpenAITransportStub;

function curl_init(string $url)
{
    OpenAITransportStub::$lastUrl = $url;
    return 'curl-resource';
}

function curl_setopt($ch, int $option, mixed $value): bool
{
    return true;
}

function curl_exec($ch): string|false
{
    return OpenAITransportStub::$response;
}

function curl_getinfo($ch, int $option): int
{
    return $option === \CURLINFO_HTTP_CODE ? OpenAITransportStub::$httpCode : 200;
}

function curl_error($ch): string
{
    return OpenAITransportStub::$error;
}

function curl_close($ch): void
{
}
