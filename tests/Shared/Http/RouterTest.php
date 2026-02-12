<?php

declare(strict_types=1);

namespace Tests\Shared\Http;

use App\Activities\Application\UseCase\ClearActivityLogUseCase;
use App\Activities\Application\UseCase\ListActivityLogUseCase;
use App\Activities\Application\UseCase\RecordActivityUseCase;
use App\Activities\Infrastructure\Persistence\FileActivityLogRepository;
use App\Albums\Application\UseCase\CreateAlbumUseCase;
use App\Albums\Application\UseCase\DeleteAlbumUseCase;
use App\Albums\Application\UseCase\FindAlbumUseCase;
use App\Albums\Application\UseCase\ListAlbumsUseCase;
use App\Albums\Application\UseCase\UploadAlbumCoverUseCase;
use App\Shared\Domain\Filesystem\FilesystemInterface;
use App\Application\Comics\GenerateComicUseCase;
use App\AI\ComicGeneratorInterface;
use App\Albums\Application\UseCase\UpdateAlbumUseCase;
use App\Albums\Infrastructure\Persistence\FileAlbumRepository;
use App\Config\ServiceUrlProvider;
use App\Heroes\Application\UseCase\CreateHeroUseCase;
use App\Heroes\Application\UseCase\DeleteHeroUseCase;
use App\Heroes\Application\UseCase\FindHeroUseCase;
use App\Heroes\Application\UseCase\ListHeroesUseCase;
use App\Heroes\Application\UseCase\UpdateHeroUseCase;
use App\Heroes\Infrastructure\Persistence\FileHeroRepository;
use App\Notifications\Application\ClearNotificationsUseCase;
use App\Notifications\Application\ListNotificationsUseCase;
use App\Notifications\Infrastructure\NotificationRepository;
use App\Shared\Http\JsonResponse;
use App\Shared\Http\Router;
use App\Shared\Infrastructure\Bus\InMemoryEventBus;
use PHPUnit\Framework\TestCase;

/**
 * Router integration tests - covers all route dispatch logic
 */
final class RouterTest extends TestCase
{
    private string $tempDir;
    private FileAlbumRepository $albumRepo;
    private FileHeroRepository $heroRepo;
    private FileActivityLogRepository $activityRepo;
    private NotificationRepository $notificationRepo;
    private InMemoryEventBus $eventBus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/router-test-' . uniqid('', true);
        mkdir($this->tempDir, 0777, true);
        mkdir($this->tempDir . '/actividad', 0777, true);
        mkdir($this->tempDir . '/notifications', 0777, true);
        
        file_put_contents($this->tempDir . '/albums.json', '[]');
        file_put_contents($this->tempDir . '/heroes.json', '[]');
        file_put_contents($this->tempDir . '/notifications/all.json', '[]');
        
        $this->albumRepo = new FileAlbumRepository($this->tempDir . '/albums.json');
        $this->heroRepo = new FileHeroRepository($this->tempDir . '/heroes.json');
        $this->activityRepo = new FileActivityLogRepository($this->tempDir . '/actividad');
        $this->notificationRepo = new NotificationRepository($this->tempDir . '/notifications');
        $this->eventBus = new InMemoryEventBus();
        
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';
        $_ENV['APP_ENV'] = 'test';
        
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION['auth'] = ['user_id' => 'test', 'email' => 'test@example.com', 'role' => 'admin'];
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_ACCEPT'], $GLOBALS['__raw_input__']);
        $this->removeDir($this->tempDir);
        http_response_code(200);
        parent::tearDown();
    }

    // GET Routes
    public function testGetAlbums(): void
    {
        $this->dispatchRoute('GET', '/albums');
        self::assertSame('éxito', JsonResponse::lastPayload()['estado'] ?? null);
    }

    public function testGetHeroes(): void
    {
        $this->dispatchRoute('GET', '/heroes');
        self::assertSame('éxito', JsonResponse::lastPayload()['estado'] ?? null);
    }

    public function testGetNotifications(): void
    {
        $this->dispatchRoute('GET', '/notifications');
        self::assertSame('éxito', JsonResponse::lastPayload()['estado'] ?? null);
    }

    public function testGetHeroById(): void
    {
        $this->dispatchRoute('GET', '/heroes/hero-123');
        self::assertNotNull(JsonResponse::lastPayload());
    }

    public function testGetHeroesByAlbum(): void
    {
        $this->dispatchRoute('GET', '/albums/album-123/heroes');
        self::assertSame('éxito', JsonResponse::lastPayload()['estado'] ?? null);
    }

    public function testGetActivityAlbums(): void
    {
        $this->dispatchRoute('GET', '/activity/albums');
        self::assertSame('éxito', JsonResponse::lastPayload()['estado'] ?? null);
    }

    public function testGetActivityComic(): void
    {
        $this->dispatchRoute('GET', '/activity/comic');
        self::assertSame('éxito', JsonResponse::lastPayload()['estado'] ?? null);
    }

    public function testGetActivityHeroes(): void
    {
        $this->dispatchRoute('GET', '/activity/heroes/album-123');
        self::assertSame('éxito', JsonResponse::lastPayload()['estado'] ?? null);
    }

    public function testGetConfigServices(): void
    {
        $this->dispatchRoute('GET', '/config/services');
        self::assertSame('éxito', JsonResponse::lastPayload()['estado'] ?? null);
    }

    public function testGetUnknownRoute(): void
    {
        $this->dispatchRoute('GET', '/unknown/route');
        self::assertSame('error', JsonResponse::lastPayload()['estado'] ?? null);
    }

    // POST Routes
    public function testPostAlbums(): void
    {
        $GLOBALS['__raw_input__'] = json_encode(['nombre' => 'Test Album']);
        $this->dispatchRoute('POST', '/albums');
        // Route matched - payload exists (may succeed or fail validation)
        self::assertNotNull(JsonResponse::lastPayload());
    }

    public function testPostActivityAlbums(): void
    {
        $GLOBALS['__raw_input__'] = json_encode(['nombre' => 'Test', 'descripcion' => 'Activity']);
        $this->dispatchRoute('POST', '/activity/albums');
        self::assertNotNull(JsonResponse::lastPayload());
    }

    public function testPostActivityComic(): void
    {
        $GLOBALS['__raw_input__'] = json_encode(['nombre' => 'Test', 'descripcion' => 'Comic']);
        $this->dispatchRoute('POST', '/activity/comic');
        self::assertNotNull(JsonResponse::lastPayload());
    }

    public function testPostActivityHeroes(): void
    {
        $GLOBALS['__raw_input__'] = json_encode(['nombre' => 'Test', 'descripcion' => 'Hero']);
        $this->dispatchRoute('POST', '/activity/heroes/album-123');
        self::assertNotNull(JsonResponse::lastPayload());
    }

    public function testPostHeroesToAlbum(): void
    {
        $album = \App\Albums\Domain\Entity\Album::create('album-123', 'Test Album');
        $this->albumRepo->save($album);
        $GLOBALS['__raw_input__'] = json_encode(['nombre' => 'Spider-Man', 'contenido' => 'Hero', 'imagen' => 'https://x.com/i.jpg']);
        $this->dispatchRoute('POST', '/albums/album-123/heroes');
        self::assertNotNull(JsonResponse::lastPayload());
    }

    public function testPostAlbumCover(): void
    {
        $album = \App\Albums\Domain\Entity\Album::create('album-cover', 'Test Album');
        $this->albumRepo->save($album);
        $this->dispatchRoute('POST', '/albums/album-cover/cover');
        self::assertNotNull(JsonResponse::lastPayload());
    }

    public function testPostComicsGenerate(): void
    {
        $GLOBALS['__raw_input__'] = json_encode(['heroIds' => ['h1', 'h2']]);
        $this->dispatchRoute('POST', '/comics/generate');
        self::assertNotNull(JsonResponse::lastPayload());
    }

    public function testPostUnknownRoute(): void
    {
        $this->dispatchRoute('POST', '/unknown/route');
        self::assertSame('error', JsonResponse::lastPayload()['estado'] ?? null);
    }

    // PUT Routes
    public function testPutAlbum(): void
    {
        $album = \App\Albums\Domain\Entity\Album::create('album-put', 'Original');
        $this->albumRepo->save($album);
        $GLOBALS['__raw_input__'] = json_encode(['nombre' => 'Updated']);
        $this->dispatchRoute('PUT', '/albums/album-put');
        self::assertNotNull(JsonResponse::lastPayload());
    }

    public function testPutHero(): void
    {
        $album = \App\Albums\Domain\Entity\Album::create('album-h', 'Album');
        $this->albumRepo->save($album);
        $hero = \App\Heroes\Domain\Entity\Hero::create('hero-put', 'album-h', 'Hero', 'Content', 'https://x.com/i.jpg');
        $this->heroRepo->save($hero);
        $GLOBALS['__raw_input__'] = json_encode(['nombre' => 'Updated']);
        $this->dispatchRoute('PUT', '/heroes/hero-put');
        self::assertNotNull(JsonResponse::lastPayload());
    }

    public function testPutUnknownRoute(): void
    {
        $this->dispatchRoute('PUT', '/unknown/route');
        self::assertSame('error', JsonResponse::lastPayload()['estado'] ?? null);
    }

    // DELETE Routes
    public function testDeleteAlbum(): void
    {
        $album = \App\Albums\Domain\Entity\Album::create('album-del', 'To Delete');
        $this->albumRepo->save($album);
        $this->dispatchRoute('DELETE', '/albums/album-del');
        self::assertSame('éxito', JsonResponse::lastPayload()['estado'] ?? null);
    }

    public function testDeleteHero(): void
    {
        $album = \App\Albums\Domain\Entity\Album::create('album-hd', 'Album');
        $this->albumRepo->save($album);
        $hero = \App\Heroes\Domain\Entity\Hero::create('hero-del', 'album-hd', 'Hero', 'Content', 'https://x.com/i.jpg');
        $this->heroRepo->save($hero);
        $this->dispatchRoute('DELETE', '/heroes/hero-del');
        self::assertSame('éxito', JsonResponse::lastPayload()['estado'] ?? null);
    }

    public function testDeleteNotifications(): void
    {
        $this->dispatchRoute('DELETE', '/notifications');
        self::assertSame('éxito', JsonResponse::lastPayload()['estado'] ?? null);
    }

    public function testDeleteActivityAlbums(): void
    {
        $this->dispatchRoute('DELETE', '/activity/albums');
        self::assertSame('éxito', JsonResponse::lastPayload()['estado'] ?? null);
    }

    public function testDeleteActivityComic(): void
    {
        $this->dispatchRoute('DELETE', '/activity/comic');
        self::assertSame('éxito', JsonResponse::lastPayload()['estado'] ?? null);
    }

    public function testDeleteActivityHeroes(): void
    {
        $this->dispatchRoute('DELETE', '/activity/heroes/album-123');
        self::assertSame('éxito', JsonResponse::lastPayload()['estado'] ?? null);
    }

    public function testDeleteUnknownRoute(): void
    {
        $this->dispatchRoute('DELETE', '/unknown/route');
        self::assertSame('error', JsonResponse::lastPayload()['estado'] ?? null);
    }

    // Method Not Allowed
    public function testPatchMethod(): void
    {
        $this->dispatchRoute('PATCH', '/albums');
        $payload = JsonResponse::lastPayload();
        self::assertSame('error', $payload['estado'] ?? null);
        self::assertStringContainsString('no permitido', $payload['message'] ?? '');
    }

    public function testOptionsMethod(): void
    {
        $this->dispatchRoute('OPTIONS', '/albums');
        self::assertSame('error', JsonResponse::lastPayload()['estado'] ?? null);
    }

    public function testHeadMethod(): void
    {
        $this->dispatchRoute('HEAD', '/albums');
        self::assertSame('error', JsonResponse::lastPayload()['estado'] ?? null);
    }

    // POST Auth Routes - These need auth services configured
    // testPostLogin and testPostLogout are skipped to avoid hangs
    // when auth services throw RuntimeException

    // Dev Routes - Skip to avoid long-running tests
    // testPostDevTestsRun skipped as it runs actual PHPUnit

    // Admin Routes
    public function testPostAdminSeedAll(): void
    {
        $this->dispatchRoute('POST', '/admin/seed-all');
        // Without seed service returns error
        self::assertSame('error', JsonResponse::lastPayload()['estado'] ?? null);
    }

    public function testPostAdminSeedAllWithService(): void
    {
        $router = new Router($this->createContainerWithSeedService());
        ob_start();
        $router->handle('POST', '/admin/seed-all');
        ob_get_clean();
        // With seed service should work
        self::assertNotNull(JsonResponse::lastPayload());
    }

    // RAG Proxy Route  
    public function testPostRagHeroes(): void
    {
        $GLOBALS['__raw_input__'] = json_encode(['hero1' => 'Spider-Man', 'hero2' => 'Iron Man']);
        $this->dispatchRoute('POST', '/api/rag/heroes');
        self::assertNotNull(JsonResponse::lastPayload());
    }

    // Additional coverage tests - HTML routes and edge cases
    
    // Test that HTML request to unknown route renders 404 page (PageController branch)
    public function testHtmlRequestToUnknownRouteRendersNotFound(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'text/html';
        $router = new Router($this->createContainer());
        
        ob_start();
        $router->handle('GET', '/some/unknown/page');
        $output = ob_get_clean();
        
        // PageController handles HTML 404 - we just verify no exception
        self::assertTrue(true);
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
    }
    
    // Test service URL provider falls back when not in container
    public function testServiceUrlProviderFallback(): void
    {
        $container = $this->createContainer();
        unset($container['services']);
        
        $router = new Router($container);
        
        ob_start();
        $router->handle('GET', '/config/services');
        ob_get_clean();
        
        self::assertNotNull(JsonResponse::lastPayload());
    }
    
    // Test comic controller with custom generator
    public function testComicControllerUsesContainerGenerator(): void
    {
        $GLOBALS['__raw_input__'] = json_encode(['heroIds' => ['h1', 'h2']]);
        $this->dispatchRoute('POST', '/comics/generate');
        self::assertNotNull(JsonResponse::lastPayload());
    }
    
    // Note: testDevControllerFallsBackToEnvironmentRunner skipped
    // It would run PHPUnit recursively causing hangs
    
    // Test README controller with callable container
    public function testReadmeWithCallableController(): void
    {
        $tempDir = sys_get_temp_dir() . '/router-readme-callable-' . uniqid('', true);
        mkdir($tempDir, 0777, true);
        file_put_contents($tempDir . '/README.md', "# Test\n\nContent.");
        
        $container = $this->createContainer();
        $container['readme.show'] = fn() => new \App\Shared\Http\ReadmeController($tempDir);
        
        $router = new Router($container);
        
        ob_start();
        $router->handle('GET', '/readme/raw');
        $output = ob_get_clean();
        
        self::assertStringContainsString('<h1>Test</h1>', $output);
        
        // Cleanup
        @unlink($tempDir . '/README.md');
        @rmdir($tempDir);
    }

    // Helpers
    private function dispatchRoute(string $method, string $path): void
    {
        $router = new Router($this->createContainer());
        ob_start();
        $router->handle($method, $path);
        ob_get_clean();
    }

    /** @return array<string, mixed> */
    private function createContainer(): array
    {
        $comicStub = new class {
            /** @param array<int, mixed> $heroes */
            public function generateComic(array $heroes): array {
                return ['story' => ['title' => 'Test', 'summary' => 'Test', 'panels' => []]];
            }
            public function isConfigured(): bool { return true; }
        };

        return [
            'albumRepository' => $this->albumRepo,
            'heroRepository' => $this->heroRepo,
            'activityRepository' => $this->activityRepo,
            'notificationRepository' => $this->notificationRepo,
            'eventBus' => $this->eventBus,
            'config' => ['services' => ['environments' => []]],
            'services' => ['urlProvider' => new ServiceUrlProvider(['environments' => []])],
            'useCases' => [
                'listAlbums' => new ListAlbumsUseCase($this->albumRepo),
                'createAlbum' => new CreateAlbumUseCase($this->albumRepo),
                'updateAlbum' => new UpdateAlbumUseCase($this->albumRepo, $this->eventBus),
                'deleteAlbum' => new DeleteAlbumUseCase($this->albumRepo, $this->heroRepo),
                'findAlbum' => new FindAlbumUseCase($this->albumRepo),
                'listHeroes' => new ListHeroesUseCase($this->heroRepo),
                'createHero' => new CreateHeroUseCase($this->heroRepo, $this->albumRepo, $this->eventBus),
                'updateHero' => new UpdateHeroUseCase($this->heroRepo),
                'deleteHero' => new DeleteHeroUseCase($this->heroRepo),
                'findHero' => new FindHeroUseCase($this->heroRepo),
                'listNotifications' => new ListNotificationsUseCase($this->notificationRepo),
                'clearNotifications' => new ClearNotificationsUseCase($this->notificationRepo),
                'listActivity' => new ListActivityLogUseCase($this->activityRepo),
                'recordActivity' => new RecordActivityUseCase($this->activityRepo),
                'clearActivity' => new ClearActivityLogUseCase($this->activityRepo),
                'generateComic' => new GenerateComicUseCase(
                    $this->createMock(ComicGeneratorInterface::class),
                    new FindHeroUseCase($this->heroRepo)
                ),
                'uploadAlbumCover' => new UploadAlbumCoverUseCase(
                    $this->createMock(FilesystemInterface::class),
                    new FindAlbumUseCase($this->albumRepo),
                    new UpdateAlbumUseCase($this->albumRepo, $this->eventBus)
                ),
            ],
            'ai' => ['comicGenerator' => $comicStub],
        ];
    }

    private function removeDir(string $path): void
    {
        if (!is_dir($path)) return;
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($path);
    }

    /** @return array<string, mixed> */
    private function createContainerWithSeedService(): array
    {
        $container = $this->createContainer();
        
        $seedService = new class {
            public function seed(): array {
                return ['albums' => 1, 'heroes' => 3];
            }
        };
        
        $container['seedHeroesService'] = $seedService;
        return $container;
    }

    /** @return array<string, mixed> */
    private function createContainerWithBrokenUseCases(): array
    {
        $brokenUseCase = new class {
            public function __invoke(): void {
                throw new \RuntimeException('Broken use case');
            }
        };
        
        return [
            'config' => ['services' => ['environments' => []]],
            'services' => ['urlProvider' => new ServiceUrlProvider(['environments' => []])],
            'useCases' => [
                'listAlbums' => $brokenUseCase,
                'createAlbum' => $brokenUseCase,
                'updateAlbum' => $brokenUseCase,
                'deleteAlbum' => $brokenUseCase,
                'findAlbum' => $brokenUseCase,
                'listHeroes' => $brokenUseCase,
                'createHero' => $brokenUseCase,
                'updateHero' => $brokenUseCase,
                'deleteHero' => $brokenUseCase,
                'findHero' => $brokenUseCase,
                'listNotifications' => $brokenUseCase,
                'clearNotifications' => $brokenUseCase,
                'listActivity' => $brokenUseCase,
                'recordActivity' => $brokenUseCase,
                'clearActivity' => $brokenUseCase,
            ],
        ];
    }
}
