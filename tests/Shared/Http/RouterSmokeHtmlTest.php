<?php

declare(strict_types=1);

namespace Tests\Shared\Http;

use App\Albums\Application\UseCase\CreateAlbumUseCase;
use App\Albums\Application\UseCase\DeleteAlbumUseCase;
use App\Albums\Application\UseCase\FindAlbumUseCase;
use App\Albums\Application\UseCase\ListAlbumsUseCase;
use App\Albums\Application\UseCase\UpdateAlbumUseCase;
use App\Albums\Domain\Entity\Album;
use App\Albums\Domain\Repository\AlbumRepository;
use App\Application\Security\IpBlockerService;
use App\Application\Security\LoginAttemptService;
use App\Config\SecurityConfig;
use App\Controllers\Http\Request;
use App\Heroes\Domain\Repository\HeroRepository;
use App\Security\Auth\AuthService;
use App\Security\Http\CsrfTokenManager;
use App\Shared\Http\Router;
use App\Shared\Infrastructure\Bus\InMemoryEventBus;
use PHPUnit\Framework\TestCase;
use Tests\Support\HttpRequestHarness;

final class RouterSmokeHtmlTest extends TestCase
{
    protected function tearDown(): void
    {
        HttpRequestHarness::resetGlobals();
        Request::withJsonBody('');
        unset($GLOBALS['__clean_marvel_container']);
        parent::tearDown();
    }

    /**
     * The view is included via `require_once`, so this must run isolated to ensure it renders output.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGetLoginRendersHtml(): void
    {
        $container = $this->containerWithAuthOnly();

        $result = HttpRequestHarness::dispatch(
            function () use ($container): void {
                $GLOBALS['__clean_marvel_container'] = $container;
                (new Router($container))->handle('GET', '/login');
            },
            [
                'HTTP_ACCEPT' => 'text/html',
                'REMOTE_ADDR' => '127.0.0.1',
                'HTTP_USER_AGENT' => 'PHPUnit',
            ],
        );

        self::assertSame(200, $result['status']);
        self::assertStringContainsString('Secret Room', $result['output']);
        self::assertStringContainsString('type="password"', $result['output']);
    }

    public function testGetUnknownHtmlRouteReturns404WithoutFatal(): void
    {
        $container = $this->containerWithAuthOnly();

        $result = HttpRequestHarness::dispatch(
            function () use ($container): void {
                $GLOBALS['__clean_marvel_container'] = $container;
                (new Router($container))->handle('GET', '/ruta-inexistente');
            },
            [
                'HTTP_ACCEPT' => 'text/html',
                'REMOTE_ADDR' => '127.0.0.1',
                'HTTP_USER_AGENT' => 'PHPUnit',
            ],
        );

        self::assertSame(404, $result['status']);
    }

    public function testJsonExceptionGetsConvertedToControlled500Payload(): void
    {
        $container = $this->containerWithAlbumRouteThatThrows();

        $result = HttpRequestHarness::dispatch(
            function () use ($container): void {
                $GLOBALS['__clean_marvel_container'] = $container;
                (new Router($container))->handle('GET', '/albums');
            },
            [
                'HTTP_ACCEPT' => 'application/json',
                'REMOTE_ADDR' => '127.0.0.1',
                'HTTP_USER_AGENT' => 'PHPUnit',
            ],
        );

        self::assertIsArray($result['payload']);
        self::assertSame('error', $result['payload']['estado'] ?? null);
        self::assertSame('Error inesperado en el servidor.', $result['payload']['message'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private function containerWithAuthOnly(): array
    {
        $securityConfig = new SecurityConfig();
        $authService = new AuthService($securityConfig);

        return [
            'security' => [
                'auth' => $authService,
                'csrf' => new CsrfTokenManager('test'),
                'ipBlocker' => new IpBlockerService(new LoginAttemptService()),
            ],
            'useCases' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function containerWithAlbumRouteThatThrows(): array
    {
        $albumRepo = new class implements AlbumRepository {
            public function save(Album $album): void {}
            public function all(): array { throw new \RuntimeException('boom'); }
            public function find(string $albumId): ?Album { return null; }
            public function delete(string $albumId): void {}
            public function seed(Album ...$albums): void {}
        };
        $heroRepo = new class implements HeroRepository {
            public function save(\App\Heroes\Domain\Entity\Hero $hero): void {}
            public function find(string $heroId): ?\App\Heroes\Domain\Entity\Hero { return null; }
            public function delete(string $heroId): void {}
            public function all(): array { return []; }
            public function byAlbum(string $albumId): array { return []; }
            public function deleteByAlbum(string $albumId): void {}
        };
        $eventBus = new InMemoryEventBus();

        return [
            'useCases' => [
                'listAlbums' => new ListAlbumsUseCase($albumRepo),
                'createAlbum' => new CreateAlbumUseCase($albumRepo),
                'updateAlbum' => new UpdateAlbumUseCase($albumRepo, $eventBus),
                'deleteAlbum' => new DeleteAlbumUseCase($albumRepo, $heroRepo),
                'findAlbum' => new FindAlbumUseCase($albumRepo),
            ],
        ];
    }
}
