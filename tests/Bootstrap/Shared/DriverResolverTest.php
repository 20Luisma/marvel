<?php

declare(strict_types=1);

namespace Tests\Bootstrap\Shared;

use App\Bootstrap\Shared\DriverResolver;
use PHPUnit\Framework\TestCase;

final class DriverResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->clearEnvironment();
    }

    protected function tearDown(): void
    {
        $this->clearEnvironment();
        parent::tearDown();
    }

    private function clearEnvironment(): void
    {
        unset($_ENV['TEST_DRIVER']);
        putenv('TEST_DRIVER');
    }

    public function testResolveReturnsEnvValueWhenSet(): void
    {
        $_ENV['TEST_DRIVER'] = 'file';
        
        $result = DriverResolver::resolve('TEST_DRIVER', 'local');
        
        self::assertSame('file', $result);
    }

    public function testResolveReturnsDbWhenEnvIsDb(): void
    {
        $_ENV['TEST_DRIVER'] = 'db';
        
        $result = DriverResolver::resolve('TEST_DRIVER', 'local');
        
        self::assertSame('db', $result);
    }

    public function testResolveNormalizesToLowercase(): void
    {
        $_ENV['TEST_DRIVER'] = 'FILE';
        
        $result = DriverResolver::resolve('TEST_DRIVER', 'local');
        
        self::assertSame('file', $result);
    }

    public function testResolveTrimsWhitespace(): void
    {
        $_ENV['TEST_DRIVER'] = '  file  ';
        
        $result = DriverResolver::resolve('TEST_DRIVER', 'local');
        
        self::assertSame('file', $result);
    }

    public function testResolveReturnsDefaultForInvalidValue(): void
    {
        $_ENV['TEST_DRIVER'] = 'invalid';
        
        $result = DriverResolver::resolve('TEST_DRIVER', 'local', 'db');
        
        self::assertSame('db', $result);
    }

    public function testResolveReturnsFileForTestEnvironmentWhenEnvNotSet(): void
    {
        $result = DriverResolver::resolve('TEST_DRIVER', 'test');
        
        self::assertSame('file', $result);
    }

    public function testResolveReturnsDefaultForNonTestEnvironmentWhenEnvNotSet(): void
    {
        $result = DriverResolver::resolve('TEST_DRIVER', 'local', 'db');
        
        self::assertSame('db', $result);
    }

    public function testResolveReturnsDefaultForEmptyEnvValue(): void
    {
        $_ENV['TEST_DRIVER'] = '';
        
        $result = DriverResolver::resolve('TEST_DRIVER', 'local', 'db');
        
        self::assertSame('db', $result);
    }

    public function testResolveReturnsDefaultForWhitespaceOnlyEnvValue(): void
    {
        $_ENV['TEST_DRIVER'] = '   ';
        
        $result = DriverResolver::resolve('TEST_DRIVER', 'local', 'db');
        
        self::assertSame('db', $result);
    }

    public function testResolveUsesGetenvWhenEnvNotInArray(): void
    {
        putenv('TEST_DRIVER=file');
        unset($_ENV['TEST_DRIVER']);
        
        $result = DriverResolver::resolve('TEST_DRIVER', 'local');
        
        self::assertSame('file', $result);
    }

    public function testResolveWithMixedCaseDb(): void
    {
        $_ENV['TEST_DRIVER'] = 'Db';
        
        $result = DriverResolver::resolve('TEST_DRIVER', 'local');
        
        self::assertSame('db', $result);
    }

    public function testResolveDefaultsToDbWhenNoDefaultProvided(): void
    {
        $result = DriverResolver::resolve('NONEXISTENT_DRIVER', 'local');
        
        self::assertSame('db', $result);
    }

    public function testResolveWithProductionEnvironment(): void
    {
        $result = DriverResolver::resolve('TEST_DRIVER', 'production', 'db');
        
        self::assertSame('db', $result);
    }
}
