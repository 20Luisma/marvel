<?php

declare(strict_types=1);

namespace Tests\Heatmap;

require_once __DIR__ . '/HttpHeatmapCurlStubs.php';

use App\Heatmap\Infrastructure\HttpHeatmapApiClient;
use App\Heatmap\Infrastructure\HttpHeatmapApiClientTestDoubles;
use PHPUnit\Framework\TestCase;

final class HttpHeatmapApiClientTest extends TestCase
{
    protected function setUp(): void
    {
        HttpHeatmapApiClientTestDoubles::reset();
    }

    public function testReturns502WhenCurlInitializationFails(): void
    {
        HttpHeatmapApiClientTestDoubles::$forceCurlInitFailure = true;

        $client = new HttpHeatmapApiClient('http://heatmap.local');
        $response = $client->getPages();

        self::assertSame(502, $response['statusCode']);
        self::assertStringContainsString('Heatmap microservice unavailable', $response['body']);
    }

    public function testReturns400WhenPayloadCannotBeSerialized(): void
    {
        $client = new HttpHeatmapApiClient('http://heatmap.local');
        HttpHeatmapApiClientTestDoubles::$curlExecReturn = false;
        HttpHeatmapApiClientTestDoubles::$curlError = 'simulated error';

        $response = $client->getPages();

        self::assertSame(502, $response['statusCode']);
        self::assertStringContainsString('Heatmap microservice unavailable', $response['body']);
    }
}
