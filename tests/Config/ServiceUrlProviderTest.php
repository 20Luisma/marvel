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
        unset($_ENV['RAG_BASE_URL'], $_ENV['RAG_SERVICE_URL']);
    }

    protected function tearDown(): void
    {
        unset($_ENV['APP_ENV'], $_ENV['RAG_BASE_URL'], $_ENV['RAG_SERVICE_URL']);
        putenv('APP_ENV');
        putenv('RAG_BASE_URL');
        putenv('RAG_SERVICE_URL');
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

    public function testResolveEnvironmentReturnsDefaultWhenHostDoesNotMatch(): void
    {
        $provider = new ServiceUrlProvider($this->config);

        self::assertSame('local', $provider->resolveEnvironment('unknown.example.com'));
    }

    public function testGetRagBaseUrlUsesEnvironmentVariableOverride(): void
    {
        $_ENV['RAG_BASE_URL'] = 'https://override.example.com';
        putenv('RAG_BASE_URL=https://override.example.com');
        $provider = new ServiceUrlProvider($this->config);

        self::assertSame('https://override.example.com', $provider->getRagBaseUrl('hosting'));
    }

    public function testGetRagHeroesUrlUsesEnvironmentVariableOverride(): void
    {
        $_ENV['RAG_SERVICE_URL'] = 'https://service-override.example.com/heroes';
        putenv('RAG_SERVICE_URL=https://service-override.example.com/heroes');
        $provider = new ServiceUrlProvider($this->config);

        self::assertSame('https://service-override.example.com/heroes', $provider->getRagHeroesUrl('hosting'));
    }

    public function testGetOpenAiChatUrlFallsBackToDefaultPathWhenNotConfigured(): void
    {
        $config = [
            'default_environment' => 'custom',
            'environments' => [
                'custom' => [
                    'openai' => [
                        'base_url' => 'https://api.example.com',
                    ],
                ],
            ],
        ];

        $provider = new ServiceUrlProvider($config);

        self::assertSame('https://api.example.com/v1/chat', $provider->getOpenAiChatUrl('custom'));
    }
}
