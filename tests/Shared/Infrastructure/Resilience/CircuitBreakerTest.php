<?php

declare(strict_types=1);

namespace Tests\Shared\Infrastructure\Resilience;

use App\Shared\Infrastructure\Resilience\CircuitBreaker;
use App\Shared\Infrastructure\Resilience\CircuitBreakerOpenException;
use PHPUnit\Framework\TestCase;

final class CircuitBreakerTest extends TestCase
{
    private string $testStoragePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testStoragePath = sys_get_temp_dir() . '/circuit-breaker-test-' . uniqid();
        @mkdir($this->testStoragePath, 0775, true);
    }

    protected function tearDown(): void
    {
        // Clean up test files
        $files = glob($this->testStoragePath . '/*');
        if (is_array($files)) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
        @rmdir($this->testStoragePath);
        parent::tearDown();
    }

    public function testCircuitStartsInClosedState(): void
    {
        $cb = new CircuitBreaker('test-closed', storagePath: $this->testStoragePath);

        $state = $cb->getState();

        self::assertSame('closed', $state['state']);
        self::assertSame(0, $state['failure_count']);
    }

    public function testSuccessfulOperationKeepsCircuitClosed(): void
    {
        $cb = new CircuitBreaker('test-success', storagePath: $this->testStoragePath);

        $result = $cb->execute(fn() => 'success');

        self::assertSame('success', $result);
        self::assertSame('closed', $cb->getState()['state']);
    }

    public function testFailuresIncrementCounter(): void
    {
        $cb = new CircuitBreaker('test-failures', failureThreshold: 3, storagePath: $this->testStoragePath);

        // First failure
        try {
            $cb->execute(fn() => throw new \RuntimeException('fail'));
        } catch (\RuntimeException) {
        }

        self::assertSame(1, $cb->getState()['failure_count']);
        self::assertSame('closed', $cb->getState()['state']);

        // Second failure
        try {
            $cb->execute(fn() => throw new \RuntimeException('fail'));
        } catch (\RuntimeException) {
        }

        self::assertSame(2, $cb->getState()['failure_count']);
        self::assertSame('closed', $cb->getState()['state']);
    }

    public function testCircuitOpensAfterThresholdReached(): void
    {
        $cb = new CircuitBreaker('test-open', failureThreshold: 2, storagePath: $this->testStoragePath);

        // Two failures to reach threshold
        for ($i = 0; $i < 2; $i++) {
            try {
                $cb->execute(fn() => throw new \RuntimeException('fail'));
            } catch (\RuntimeException) {
            }
        }

        self::assertSame('open', $cb->getState()['state']);
        self::assertTrue($cb->isOpen());
    }

    public function testOpenCircuitThrowsExceptionWithoutFallback(): void
    {
        $cb = new CircuitBreaker('test-exception', failureThreshold: 1, storagePath: $this->testStoragePath);

        // Trip the circuit
        try {
            $cb->execute(fn() => throw new \RuntimeException('fail'));
        } catch (\RuntimeException) {
        }

        self::assertTrue($cb->isOpen());

        $this->expectException(CircuitBreakerOpenException::class);
        $this->expectExceptionMessage("Circuit breaker 'test-exception' is open");

        $cb->execute(fn() => 'should not run');
    }

    public function testOpenCircuitUsesFallbackWhenProvided(): void
    {
        $cb = new CircuitBreaker('test-fallback', failureThreshold: 1, storagePath: $this->testStoragePath);

        // Trip the circuit
        try {
            $cb->execute(fn() => throw new \RuntimeException('fail'));
        } catch (\RuntimeException) {
        }

        $result = $cb->execute(
            fn() => 'should not run',
            fn() => 'fallback value'
        );

        self::assertSame('fallback value', $result);
    }

    public function testCircuitTransitionsToHalfOpenAfterTimeout(): void
    {
        $cb = new CircuitBreaker(
            'test-half-open',
            failureThreshold: 1,
            openTtlSeconds: 1, // 1 second for fast test
            storagePath: $this->testStoragePath
        );

        // Trip the circuit
        try {
            $cb->execute(fn() => throw new \RuntimeException('fail'));
        } catch (\RuntimeException) {
        }

        self::assertSame('open', $cb->getState()['state']);

        // Wait for TTL
        sleep(2);

        // Should allow request (transitions to half-open)
        self::assertTrue($cb->allowRequest());
        self::assertSame('half_open', $cb->getState()['state']);
    }

    public function testSuccessInHalfOpenClosesCircuit(): void
    {
        $cb = new CircuitBreaker(
            'test-recovery',
            failureThreshold: 1,
            openTtlSeconds: 1,
            storagePath: $this->testStoragePath
        );

        // Trip and wait
        try {
            $cb->execute(fn() => throw new \RuntimeException('fail'));
        } catch (\RuntimeException) {
        }
        
        sleep(2);

        // Successful call should close circuit
        $result = $cb->execute(fn() => 'recovered');

        self::assertSame('recovered', $result);
        self::assertSame('closed', $cb->getState()['state']);
        self::assertFalse($cb->isOpen());
    }

    public function testFailureInHalfOpenReopensCircuit(): void
    {
        $cb = new CircuitBreaker(
            'test-reopen',
            failureThreshold: 1,
            openTtlSeconds: 1,
            storagePath: $this->testStoragePath
        );

        // Trip, wait, then fail again
        try {
            $cb->execute(fn() => throw new \RuntimeException('fail 1'));
        } catch (\RuntimeException) {
        }
        
        sleep(2);

        // Fail in half-open state
        try {
            $cb->execute(fn() => throw new \RuntimeException('fail 2'));
        } catch (\RuntimeException) {
        }

        self::assertSame('open', $cb->getState()['state']);
    }

    public function testResetForcesCircuitClosed(): void
    {
        $cb = new CircuitBreaker('test-reset', failureThreshold: 1, storagePath: $this->testStoragePath);

        // Trip the circuit
        try {
            $cb->execute(fn() => throw new \RuntimeException('fail'));
        } catch (\RuntimeException) {
        }

        self::assertTrue($cb->isOpen());

        $cb->reset();

        self::assertFalse($cb->isOpen());
        self::assertSame('closed', $cb->getState()['state']);
        self::assertSame(0, $cb->getState()['failure_count']);
    }

    public function testMultipleCircuitBreakersAreIndependent(): void
    {
        $cb1 = new CircuitBreaker('service-a', failureThreshold: 1, storagePath: $this->testStoragePath);
        $cb2 = new CircuitBreaker('service-b', failureThreshold: 1, storagePath: $this->testStoragePath);

        // Trip cb1
        try {
            $cb1->execute(fn() => throw new \RuntimeException('fail'));
        } catch (\RuntimeException) {
        }

        self::assertTrue($cb1->isOpen());
        self::assertFalse($cb2->isOpen());

        // cb2 should still work
        $result = $cb2->execute(fn() => 'still working');
        self::assertSame('still working', $result);
    }

    public function testSuccessResetsFailureCount(): void
    {
        $cb = new CircuitBreaker('test-reset-on-success', failureThreshold: 3, storagePath: $this->testStoragePath);

        // Add some failures
        try {
            $cb->execute(fn() => throw new \RuntimeException('fail'));
        } catch (\RuntimeException) {
        }

        self::assertSame(1, $cb->getState()['failure_count']);

        // Success should reset
        $cb->execute(fn() => 'success');

        self::assertSame(0, $cb->getState()['failure_count']);
    }
}
