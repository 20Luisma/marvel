<?php

declare(strict_types=1);

namespace Creawebes\OpenAI\Tests\Infrastructure;

use Creawebes\OpenAI\Infrastructure\Client\OpenAiClientException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests for the OpenAiClientException class.
 *
 * Validates exception hierarchy, message propagation, and previous exception chaining.
 */
final class OpenAiClientExceptionTest extends TestCase
{
    public function testExtendsRuntimeException(): void
    {
        $exception = new OpenAiClientException('test error');

        $this->assertInstanceOf(RuntimeException::class, $exception);
    }

    public function testPreservesMessage(): void
    {
        $exception = new OpenAiClientException('OPENAI_API_KEY no configurada');

        $this->assertSame('OPENAI_API_KEY no configurada', $exception->getMessage());
    }

    public function testPreservesCode(): void
    {
        $exception = new OpenAiClientException('error', 42);

        $this->assertSame(42, $exception->getCode());
    }

    public function testChainsPreviousException(): void
    {
        $previous = new \Exception('original error');
        $exception = new OpenAiClientException('wrapper error', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
        $this->assertSame('wrapper error', $exception->getMessage());
        $this->assertSame('original error', $exception->getPrevious()->getMessage());
    }

    public function testCanBeCaughtAsRuntimeException(): void
    {
        $caught = false;

        try {
            throw new OpenAiClientException('should be catchable');
        } catch (RuntimeException $e) {
            $caught = true;
            $this->assertSame('should be catchable', $e->getMessage());
        }

        $this->assertTrue($caught, 'OpenAiClientException should be catchable as RuntimeException');
    }
}
