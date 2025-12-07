<?php

declare(strict_types=1);

namespace App\Heatmap\Infrastructure;

final class HttpHeatmapApiClientTestDoubles
{
    public static bool $forceCurlInitFailure = false;
    public static mixed $curlExecReturn = '{}';
    public static int $curlInfoStatus = 200;
    public static string $curlError = '';
    public static array $recorded = [];

    public static function reset(): void
    {
        self::$forceCurlInitFailure = false;
        self::$curlExecReturn = '{}';
        self::$curlInfoStatus = 200;
        self::$curlError = '';
        self::$recorded = [];
    }
}

function curl_init(string $url)
{
    HttpHeatmapApiClientTestDoubles::$recorded['init'][] = $url;

    if (HttpHeatmapApiClientTestDoubles::$forceCurlInitFailure) {
        return false;
    }

    return 'curl-resource';
}

function curl_setopt($ch, int $option, mixed $value): bool
{
    HttpHeatmapApiClientTestDoubles::$recorded['set'][] = [$option, $value];
    return true;
}

function curl_exec($ch)
{
    return HttpHeatmapApiClientTestDoubles::$curlExecReturn;
}

function curl_getinfo($ch, int $option): int
{
    if ($option === \CURLINFO_RESPONSE_CODE) {
        return HttpHeatmapApiClientTestDoubles::$curlInfoStatus;
    }

    return 0;
}

function curl_error($ch): string
{
    return HttpHeatmapApiClientTestDoubles::$curlError;
}

function curl_close($ch): void
{
    HttpHeatmapApiClientTestDoubles::$recorded['closed'] = true;
}

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
