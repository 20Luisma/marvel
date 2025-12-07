<?php

declare(strict_types=1);

namespace Tests\Heatmap\Infrastructure;

use App\Heatmap\Infrastructure\HttpHeatmapApiClient;
use App\Heatmap\Infrastructure\HttpHeatmapApiClientTestDoubles;
use PHPUnit\Framework\TestCase;

class HttpHeatmapApiClientTest extends TestCase
{
    protected function setUp(): void
    {
        HttpHeatmapApiClientTestDoubles::reset();
    }

    protected function tearDown(): void
    {
        HttpHeatmapApiClientTestDoubles::reset();
    }

    public function testSendClickHappyPath(): void
    {
        $client = new HttpHeatmapApiClient('https://api.example.com', 'token');
        HttpHeatmapApiClientTestDoubles::$curlExecReturn = '{"status":"saved"}';
        HttpHeatmapApiClientTestDoubles::$curlInfoStatus = 201;

        $result = $client->sendClick(['x' => 10, 'y' => 20, 'page' => '/home']);

        $this->assertEquals(201, $result['statusCode']);
        $this->assertEquals('{"status":"saved"}', $result['body']);
    }

    public function testGetSummaryError(): void
    {
        $client = new HttpHeatmapApiClient('https://api.example.com');
        HttpHeatmapApiClientTestDoubles::$curlExecReturn = false; // Simulate failure
        HttpHeatmapApiClientTestDoubles::$curlError = 'Connection refused';

        $result = $client->getSummary([]);

        $this->assertEquals(502, $result['statusCode']);
        $this->assertStringContainsString('Heatmap microservice unavailable', $result['body']);
        $this->assertStringContainsString('Connection refused', $result['body']);
    }
}
