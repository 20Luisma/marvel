<?php

declare(strict_types=1);

namespace Creawebes\Rag\Tests\Infrastructure\Observability;

use Creawebes\Rag\Infrastructure\Observability\JsonFileRagTelemetry;
use Creawebes\Rag\Infrastructure\Observability\ServerTraceIdProvider;
use PHPUnit\Framework\TestCase;

final class JsonFileRagTelemetryTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $serverBackup = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->serverBackup = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        parent::tearDown();
    }

    public function testWritesJsonLineWithExpectedFieldsAndTraceId(): void
    {
        $_SERVER['HTTP_X_TRACE_ID'] = 'trace-test-123';
        unset($_SERVER['__TRACE_ID__']);

        $logFile = $this->createTempLogPath();
        $provider = new ServerTraceIdProvider();
        $telemetry = new JsonFileRagTelemetry($provider, $logFile);

        $telemetry->log('rag.retrieve', 'vector', 123, 5);

        $this->assertFileExists($logFile);

        $lines = file($logFile, FILE_IGNORE_NEW_LINES);
        $this->assertIsArray($lines);
        $this->assertCount(1, $lines);

        $decoded = json_decode((string) $lines[0], true);
        $this->assertIsArray($decoded);

        $this->assertArrayHasKey('timestamp', $decoded);
        $this->assertArrayHasKey('trace_id', $decoded);
        $this->assertArrayHasKey('event', $decoded);
        $this->assertArrayHasKey('retriever', $decoded);
        $this->assertArrayHasKey('latency_ms', $decoded);
        $this->assertArrayHasKey('top_k', $decoded);

        $this->assertIsString($decoded['timestamp']);
        $this->assertNotSame('', $decoded['timestamp']);
        $this->assertNotFalse(strtotime($decoded['timestamp']));

        $this->assertSame('rag.retrieve', $decoded['event']);
        $this->assertSame('vector', $decoded['retriever']);
        $this->assertSame(123, $decoded['latency_ms']);
        $this->assertSame(5, $decoded['top_k']);

        $this->assertSame('trace-test-123', $decoded['trace_id']);
        $this->assertSame('trace-test-123', $provider->getTraceId());

        @unlink($logFile);
    }

    private function createTempLogPath(): string
    {
        $path = sys_get_temp_dir() . '/rag-telemetry-' . bin2hex(random_bytes(8)) . '.log';
        @unlink($path);

        return $path;
    }
}

