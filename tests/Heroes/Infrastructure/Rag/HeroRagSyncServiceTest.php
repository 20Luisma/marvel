<?php

declare(strict_types=1);

namespace Tests\Heroes\Infrastructure\Rag;

use App\Config\ServiceUrlProvider;
use App\Heroes\Domain\Entity\Hero;
use App\Heroes\Infrastructure\Rag\HeroRagSyncService;
use App\Shared\Infrastructure\Http\HttpClientInterface;
use App\Shared\Infrastructure\Http\HttpResponse;
use PHPUnit\Framework\TestCase;

final class HeroRagSyncServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        putenv('RAG_BASE_URL');
        putenv('RAG_SERVICE_URL');
        unset($_ENV['RAG_BASE_URL'], $_ENV['RAG_SERVICE_URL']);
    }

    protected function tearDown(): void
    {
        putenv('RAG_BASE_URL');
        putenv('RAG_SERVICE_URL');
        unset($_ENV['RAG_BASE_URL'], $_ENV['RAG_SERVICE_URL']);
        parent::tearDown();
    }

    public function testAutoEnvironmentForcesLocalhostToAvoidAccidentalHostingWrites(): void
    {
        $_ENV['RAG_BASE_URL'] = 'https://rag-service.contenido.creawebes.com';
        putenv('RAG_BASE_URL=https://rag-service.contenido.creawebes.com');

        $provider = new ServiceUrlProvider([
            'default_environment' => 'local',
            'environments' => [
                'local' => ['rag' => ['base_url' => 'http://localhost:8082']],
                'hosting' => ['rag' => ['base_url' => 'https://rag-service.contenido.creawebes.com']],
            ],
        ]);

        $http = new class implements HttpClientInterface {
            public string $lastUrl = '';

            public function post(string $url, ?string $payload, array $headers = [], int $timeoutSeconds = 20, int $retries = 1): HttpResponse
            {
                $this->lastUrl = $url;
                return new HttpResponse(200, '{"status":"ok"}');
            }

            public function postJson(string $url, array|string $payload, array $headers = [], int $timeoutSeconds = 20, int $retries = 1): HttpResponse
            {
                $this->lastUrl = $url;
                return new HttpResponse(200, '{"status":"ok"}');
            }

            public function get(string $url, array $headers = [], int $timeoutSeconds = 20, int $retries = 1): HttpResponse
            {
                $this->lastUrl = $url;
                return new HttpResponse(200, '{"status":"ok"}');
            }
        };

        $sync = new HeroRagSyncService($http, $provider, null, 'auto');
        $sync->sync(Hero::create('hero-1', 'album-1', 'Nuevo', 'Contenido', 'https://example.com/img.png'));

        self::assertSame('http://localhost:8082/rag/heroes/upsert', $http->lastUrl);
    }

    public function testHostingEnvironmentUsesHostingEndpoint(): void
    {
        $_ENV['RAG_BASE_URL'] = 'https://rag-service.contenido.creawebes.com';
        putenv('RAG_BASE_URL=https://rag-service.contenido.creawebes.com');

        $provider = new ServiceUrlProvider([
            'default_environment' => 'local',
            'environments' => [
                'hosting' => ['rag' => ['base_url' => 'https://rag-service.contenido.creawebes.com']],
            ],
        ]);

        $http = new class implements HttpClientInterface {
            public string $lastUrl = '';

            public function post(string $url, ?string $payload, array $headers = [], int $timeoutSeconds = 20, int $retries = 1): HttpResponse
            {
                $this->lastUrl = $url;
                return new HttpResponse(200, '{"status":"ok"}');
            }

            public function postJson(string $url, array|string $payload, array $headers = [], int $timeoutSeconds = 20, int $retries = 1): HttpResponse
            {
                $this->lastUrl = $url;
                return new HttpResponse(200, '{"status":"ok"}');
            }

            public function get(string $url, array $headers = [], int $timeoutSeconds = 20, int $retries = 1): HttpResponse
            {
                $this->lastUrl = $url;
                return new HttpResponse(200, '{"status":"ok"}');
            }
        };

        $sync = new HeroRagSyncService($http, $provider, null, 'hosting');
        $sync->sync(Hero::create('hero-1', 'album-1', 'Nuevo', 'Contenido', 'https://example.com/img.png'));

        self::assertSame('https://rag-service.contenido.creawebes.com/rag/heroes/upsert', $http->lastUrl);
    }
}

