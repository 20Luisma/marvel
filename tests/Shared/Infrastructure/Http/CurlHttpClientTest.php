<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

// Mocks for global functions
function curl_init(?string $url = null) { 
    return new \stdClass(); 
}

function curl_setopt($handle, int $option, $value): bool { 
    return true; 
}

function curl_exec($handle): string|bool {
    return $GLOBALS['curl_http_exec'] ?? '{"ok":true}';
}

function curl_getinfo($handle, int $option = 0) {
    return $GLOBALS['curl_http_code'] ?? 200;
}

function curl_close($handle): void {}

function curl_error($handle): string { 
    return $GLOBALS['curl_http_error'] ?? ''; 
}

namespace Tests\Shared\Infrastructure\Http;

use App\Shared\Infrastructure\Http\CurlHttpClient;
use PHPUnit\Framework\TestCase;

class CurlHttpClientTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['curl_http_exec']);
        unset($GLOBALS['curl_http_code']);
        unset($GLOBALS['curl_http_error']);
    }

    public function testPostJsonHappyPath(): void
    {
        $client = new CurlHttpClient('internal-token');
        $GLOBALS['curl_http_exec'] = '{"status":"ok"}';
        $GLOBALS['curl_http_code'] = 200;

        $response = $client->postJson('https://api.example.com', ['key' => 'value']);

        $this->assertEquals(200, $response->statusCode);
        $this->assertEquals('{"status":"ok"}', $response->body);
    }

    public function testPostJsonError(): void
    {
        $client = new CurlHttpClient();
        $GLOBALS['curl_http_exec'] = false;
        $GLOBALS['curl_http_error'] = 'Timeout';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Fallo al llamar al servicio: Timeout');

        $client->postJson('https://api.example.com', ['key' => 'value'], retries: 0);
    }
}
