<?php

declare(strict_types=1);

namespace Tests\Heatmap;

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
        $payload = ['bad' => fopen('php://memory', 'r')];

        $response = $client->sendClick($payload);

        self::assertSame(400, $response['statusCode']);
        self::assertStringContainsString('Invalid payload for heatmap request', $response['body']);
    }
}
