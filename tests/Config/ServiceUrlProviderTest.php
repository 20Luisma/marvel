<?php

declare(strict_types=1);

namespace Tests\Config;

use App\Config\ServiceUrlProvider;
use PHPUnit\Framework\TestCase;

final class ServiceUrlProviderTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $config;
    /**
     * @var array<string, mixed>
     */
    private static array $cachedConfig = [];

    protected function setUp(): void
    {
        if (self::$cachedConfig === []) {
            $config = require_once dirname(__DIR__, 2) . '/config/services.php';
            /** @var array<string, mixed> $resolvedConfig */
            $resolvedConfig = is_array($config)
                ? $config
                : ($GLOBALS['__clean_marvel_service_config'] ?? []);
            self::$cachedConfig = $resolvedConfig;
        }

        $this->config = self::$cachedConfig;
        unset($_ENV['APP_ENV']);
    }

    public function testResolveEnvironmentPrefersExplicitEnv(): void
    {
        $_ENV['APP_ENV'] = 'hosting';
        $provider = new ServiceUrlProvider($this->config);

        self::assertSame('hosting', $provider->resolveEnvironment('whatever'));
    }

    public function testResolveEnvironmentFallsBackToMatchingHost(): void
    {
        $provider = new ServiceUrlProvider($this->config);

        self::assertSame('local', $provider->resolveEnvironment('localhost:8080'));
    }

    public function testToArrayForFrontendIncludesServiceMatrix(): void
    {
        $provider = new ServiceUrlProvider($this->config);
        $payload = $provider->toArrayForFrontend('rag-service.contenido.creawebes.com');

        self::assertSame('hosting', $payload['environment']['mode']);
        self::assertSame('https://rag-service.contenido.creawebes.com', $payload['services']['rag']['baseUrl']);
        self::assertArrayHasKey('openai', $payload['services']);
    }
}
