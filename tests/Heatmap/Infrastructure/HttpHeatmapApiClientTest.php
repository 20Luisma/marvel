<?php

declare(strict_types=1);

namespace App\Heatmap\Infrastructure;

// Mocks for global functions
function curl_init(?string $url = null) { 
    return new \stdClass(); 
}

function curl_setopt($handle, int $option, $value): bool { 
    return true; 
}

function curl_exec($handle): string|bool {
    if (isset($GLOBALS['curl_exec_return'])) {
        return $GLOBALS['curl_exec_return'];
    }
    return '{"status":"ok"}';
}

function curl_getinfo($handle, int $option = 0) {
    if (isset($GLOBALS['curl_getinfo_return'])) {
        return $GLOBALS['curl_getinfo_return'];
    }
    return 200;
}

function curl_close($handle): void {}

function curl_error($handle): string { 
    return $GLOBALS['curl_error_return'] ?? ''; 
}

namespace Tests\Heatmap\Infrastructure;

use App\Heatmap\Infrastructure\HttpHeatmapApiClient;
use PHPUnit\Framework\TestCase;

class HttpHeatmapApiClientTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['curl_exec_return']);
        unset($GLOBALS['curl_getinfo_return']);
        unset($GLOBALS['curl_error_return']);
    }

    public function testSendClickHappyPath(): void
    {
        $client = new HttpHeatmapApiClient('https://api.example.com', 'token');
        $GLOBALS['curl_exec_return'] = '{"status":"saved"}';
        $GLOBALS['curl_getinfo_return'] = 201;

        $result = $client->sendClick(['x' => 10, 'y' => 20, 'page' => '/home']);

        $this->assertEquals(201, $result['statusCode']);
        $this->assertEquals('{"status":"saved"}', $result['body']);
    }

    public function testGetSummaryError(): void
    {
        $client = new HttpHeatmapApiClient('https://api.example.com');
        $GLOBALS['curl_exec_return'] = false; // Simulate failure
        $GLOBALS['curl_error_return'] = 'Connection refused';

        $result = $client->getSummary([]);

        $this->assertEquals(502, $result['statusCode']);
        $this->assertStringContainsString('Heatmap microservice unavailable', $result['body']);
        $this->assertStringContainsString('Connection refused', $result['body']);
    }
}
