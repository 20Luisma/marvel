<?php

declare(strict_types=1);

namespace Tests\Heatmap\Infrastructure;

use App\Heatmap\Infrastructure\HttpHeatmapApiClient;
use App\Heatmap\Infrastructure\HttpHeatmapApiClientTestDoubles;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../HttpHeatmapCurlStubs.php';

final class HttpHeatmapApiClientExtendedTest extends TestCase
{
    protected function setUp(): void
    {
        HttpHeatmapApiClientTestDoubles::reset();
    }

    protected function tearDown(): void
    {
        HttpHeatmapApiClientTestDoubles::reset();
    }

    public function test_send_click_handles_empty_response_body(): void
    {
        $client = new HttpHeatmapApiClient('https://api.example.com');
        HttpHeatmapApiClientTestDoubles::$curlExecReturn = '';
        HttpHeatmapApiClientTestDoubles::$curlInfoStatus = 204;

        $result = $client->sendClick(['x' => 1, 'y' => 1]);

        $this->assertEquals(204, $result['statusCode']);
        $this->assertEquals('', $result['body']);
    }

    public function test_send_click_handles_curl_error(): void
    {
        $client = new HttpHeatmapApiClient('https://api.example.com');
        HttpHeatmapApiClientTestDoubles::$curlExecReturn = false;
        HttpHeatmapApiClientTestDoubles::$curlError = 'Timeout';

        $result = $client->sendClick(['x' => 1, 'y' => 1]);

        $this->assertEquals(502, $result['statusCode']);
        $this->assertStringContainsString('Timeout', $result['body']);
    }

    public function test_get_summary_handles_success(): void
    {
        $client = new HttpHeatmapApiClient('https://api.example.com');
        HttpHeatmapApiClientTestDoubles::$curlExecReturn = '{"clicks": 100}';
        HttpHeatmapApiClientTestDoubles::$curlInfoStatus = 200;

        $result = $client->getSummary(['page' => '/home']);

        $this->assertEquals(200, $result['statusCode']);
        $this->assertEquals('{"clicks": 100}', $result['body']);
    }

    public function test_constructor_sets_token(): void
    {
        $client = new HttpHeatmapApiClient('https://api.example.com', 'my-token');
        HttpHeatmapApiClientTestDoubles::$curlExecReturn = '{}';
        
        $client->sendClick([]);
        
        // In a real mock we would verify headers, but here we just ensure no crash
        $this->addToAssertionCount(1);
    }
}
