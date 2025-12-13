<?php

declare(strict_types=1);

namespace Creawebes\Rag\Tests\Infrastructure\Observability;

use Creawebes\Rag\Infrastructure\Observability\ServerTraceIdProvider;
use PHPUnit\Framework\TestCase;

final class ServerTraceIdProviderTest extends TestCase
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

    public function testReusesValidHeaderTraceId(): void
    {
        $_SERVER['HTTP_X_TRACE_ID'] = 'trace_ABC-123.ok';
        unset($_SERVER['__TRACE_ID__']);

        $provider = new ServerTraceIdProvider();

        $first = $provider->getTraceId();
        $second = $provider->getTraceId();

        $this->assertSame('trace_ABC-123.ok', $first);
        $this->assertSame($first, $second);
    }

    public function testGeneratesAndCachesTraceIdWhenHeaderMissing(): void
    {
        unset($_SERVER['HTTP_X_TRACE_ID'], $_SERVER['__TRACE_ID__']);

        $provider = new ServerTraceIdProvider();

        $first = $provider->getTraceId();
        $second = $provider->getTraceId();

        $this->assertIsString($first);
        $this->assertNotSame('', $first);
        $this->assertSame($first, $second);
    }

    public function testIgnoresInvalidHeaderAndGeneratesTraceId(): void
    {
        $_SERVER['HTTP_X_TRACE_ID'] = str_repeat('a', 129);
        unset($_SERVER['__TRACE_ID__']);

        $provider = new ServerTraceIdProvider();
        $generated = $provider->getTraceId();

        $this->assertIsString($generated);
        $this->assertNotSame('', $generated);
        $this->assertNotSame($_SERVER['HTTP_X_TRACE_ID'], $generated);

        $_SERVER['HTTP_X_TRACE_ID'] = "bad\x00trace";
        unset($_SERVER['__TRACE_ID__']);

        $provider2 = new ServerTraceIdProvider();
        $generated2 = $provider2->getTraceId();

        $this->assertIsString($generated2);
        $this->assertNotSame('', $generated2);
        $this->assertNotSame($_SERVER['HTTP_X_TRACE_ID'], $generated2);
    }
}

