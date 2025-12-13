<?php

declare(strict_types=1);

namespace Tests\Bootstrap;

use App\Bootstrap\AppEnvironmentResolver;
use App\Config\ServiceUrlProvider;
use PHPUnit\Framework\TestCase;

final class AppEnvironmentResolverTest extends TestCase
{
    public function testResolvesExplicitEnvironment(): void
    {
        self::assertSame('hosting', AppEnvironmentResolver::resolve('hosting', null, 'example.com'));
        self::assertSame('local', AppEnvironmentResolver::resolve('local', null, 'example.com'));
    }

    public function testResolvesTestEnvironmentAsTest(): void
    {
        self::assertSame('test', AppEnvironmentResolver::resolve('test', null, 'example.com'));
    }

    public function testResolvesAutoUsingServiceUrlProvider(): void
    {
        $config = [
            'default_environment' => 'local',
            'environments' => [
                'local' => [
                    'app' => ['host' => 'localhost:8080', 'base_url' => 'http://localhost:8080'],
                ],
                'hosting' => [
                    'app' => ['host' => 'iamasterbigschool.contenido.creawebes.com', 'base_url' => 'https://iamasterbigschool.contenido.creawebes.com'],
                ],
            ],
        ];

        $provider = new ServiceUrlProvider($config);

        self::assertSame(
            'hosting',
            AppEnvironmentResolver::resolve('auto', $provider, 'iamasterbigschool.contenido.creawebes.com')
        );
        self::assertSame(
            'local',
            AppEnvironmentResolver::resolve('auto', $provider, 'localhost:8080')
        );
    }

    public function testResolvesEmptyAsAutoAndFallsBackToLocalWhenNoProvider(): void
    {
        self::assertSame('local', AppEnvironmentResolver::resolve('', null, 'iamasterbigschool.contenido.creawebes.com'));
        self::assertSame('local', AppEnvironmentResolver::resolve(null, null, null));
    }
}

