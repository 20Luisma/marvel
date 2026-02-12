<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\AI\ComicGeneratorInterface;
use App\AI\OpenAIComicGenerator;
use App\Config\ServiceUrlProvider;
use App\Activities\Application\UseCase\ClearActivityLogUseCase;
use App\Activities\Application\UseCase\ListActivityLogUseCase;
use App\Activities\Application\UseCase\RecordActivityUseCase;
use App\Activities\Domain\ActivityScope;
use App\Application\Security\IpBlockerService;
use App\Dev\Seed\SeedHeroesService;
use App\Dev\Test\PhpUnitTestRunner;
use App\Shared\Http\JsonResponse;
use App\Security\Auth\AuthService;
use App\Security\Http\AuthMiddleware;
use App\Security\Http\CsrfTokenManager;
use App\Security\Http\RateLimitMiddleware;
use App\Security\Http\ApiFirewall;
use App\Controllers\ActivityController;
use App\Controllers\AdminController;
use App\Controllers\AlbumController;
use App\Controllers\AuthController;
use App\Controllers\ConfigController;
use App\Controllers\ComicController;
use App\Controllers\DevController;
use App\Controllers\HealthCheckController;
use App\Controllers\HeroController;
use App\Controllers\Http\Request;
use App\Controllers\NotificationController;
use App\Controllers\PageController;
use App\Controllers\RagProxyController;
use App\Shared\Metrics\PrometheusMetrics;
use RuntimeException;
use Throwable;

final class Router
{
    /**
     * @param array<string, mixed> $container
     */
    public function __construct(private readonly array $container)
    {
    }

    public function handle(string $method, string $path): void
    {
        PrometheusMetrics::incrementRequests();

        if ($path === '/metrics') {
            if ($method !== 'GET') {
                JsonResponse::error('Método no permitido.', 405);
                return;
            }

            PrometheusMetrics::respond('clean-marvel');
            return;
        }

        // Orden de seguridad:
        // 1) ApiFirewall → bloquea patrones maliciosos
        // 2) RateLimitMiddleware → protege de abusos/DoS
        // 3) AuthMiddleware → protege rutas de administrador
        $firewall = $this->apiFirewall();
        if ($firewall !== null && !$firewall->handle($method, $path)) {
            return;
        }

        $rateLimiter = $this->rateLimitMiddleware();
        if ($rateLimiter !== null && !$rateLimiter->handle($method, $path)) {
            return;
        }

        $authMiddleware = $this->authMiddleware();
        if ($authMiddleware !== null && !$authMiddleware->checkAdminRoute($path)) {
            return;
        }

        if ($method === 'GET' && $path === '/login') {
            $this->authController()->showLogin();
            return;
        }

        $pageController = $this->pageController();

        if ($pageController->renderIfHtmlRoute($method, $path)) {
            return;
        }

        if ($method === 'GET' && Request::wantsHtml() && $path !== '/readme/raw') {
            $pageController->renderNotFound();
            return;
        }

        try {
            if (!$this->dispatch($method, $path)) {
                JsonResponse::error('Endpoint no encontrado.', 404);
            }
        } catch (Throwable $exception) {
            $traceId = $_SERVER['X_TRACE_ID'] ?? '-';
            $logDir = dirname(__DIR__, 2) . '/storage/logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0775, true);
            }
            @file_put_contents(
                $logDir . '/app-errors.log',
                sprintf(
                    "[%s] trace_id=%s path=%s method=%s error=%s file=%s:%d\n",
                    date('Y-m-d H:i:s'),
                    is_string($traceId) ? $traceId : '-',
                    $path,
                    $method,
                    $exception->getMessage(),
                    $exception->getFile(),
                    $exception->getLine()
                ),
                FILE_APPEND
            );
            JsonResponse::error('Error inesperado en el servidor.', 500);
        }
    }

    private function dispatch(string $method, string $path): bool
    {
        return match ($method) {
            'GET' => $this->handleGet($path),
            'POST' => $this->handlePost($path),
            'PUT' => $this->handlePut($path),
            'DELETE' => $this->handleDelete($path),
            default => $this->methodNotAllowed(),
        };
    }

    private function handleGet(string $path): bool
    {
        return $this->dispatchRoutes($path, $this->getGetRoutes());
    }

    private function handlePost(string $path): bool
    {
        return $this->dispatchRoutes($path, $this->getPostRoutes());
    }

    private function handlePut(string $path): bool
    {
        return $this->dispatchRoutes($path, $this->getPutRoutes());
    }

    private function handleDelete(string $path): bool
    {
        return $this->dispatchRoutes($path, $this->getDeleteRoutes());
    }

    private function methodNotAllowed(): bool
    {
        JsonResponse::error('Método no permitido.', 405);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function useCases(): array
    {
        /** @var array<string, mixed> $useCases */
        $useCases = $this->container['useCases'] ?? [];

        return $useCases;
    }

    /**
     * @return array<string, mixed>
     */
    private function security(): array
    {
        $security = $this->container['security'] ?? [];

        return is_array($security) ? $security : [];
    }

    /**
     * @return array<int, array{pattern: string, regex: bool, handler: callable}>
     */
    private function getGetRoutes(): array
    {
        return [
            [
                'pattern' => '/health',
                'regex' => false,
                'handler' => function (): void {
                    $this->healthCheckController()->check();
                },
            ],
            [
                'pattern' => '/activity/albums',
                'regex' => false,
                'handler' => function (): void {
                    $this->activityController()->index(ActivityScope::ALBUMS);
                },
            ],
            [
                'pattern' => '/activity/comic',
                'regex' => false,
                'handler' => function (): void {
                    $this->activityController()->index(ActivityScope::COMIC);
                },
            ],
            [
                'pattern' => '#^/activity/heroes/([A-Za-z0-9\\-]+)$#',
                'regex' => true,
                'handler' => function (string $heroId): void {
                    $this->activityController()->index(ActivityScope::HEROES, $heroId);
                },
            ],
            [
                'pattern' => '/albums',
                'regex' => false,
                'handler' => function (): void {
                    $this->albumController()->index();
                },
            ],
            [
                'pattern' => '/readme/raw',
                'regex' => false,
                'handler' => function (): void {
                    ($this->readmeController())();
                },
            ],
            [
                'pattern' => '/config/services',
                'regex' => false,
                'handler' => function (): void {
                    $this->configController()->services();
                },
            ],
            [
                'pattern' => '/heroes',
                'regex' => false,
                'handler' => function (): void {
                    $this->heroController()->index();
                },
            ],
            [
                'pattern' => '#^/albums/([A-Za-z0-9\\-]+)/heroes$#',
                'regex' => true,
                'handler' => function (string $albumId): void {
                    $this->heroController()->listByAlbum($albumId);
                },
            ],
            [
                'pattern' => '/notifications',
                'regex' => false,
                'handler' => function (): void {
                    $this->notificationController()->index();
                },
            ],
            [
                'pattern' => '#^/heroes/([A-Za-z0-9\\-]+)$#',
                'regex' => true,
                'handler' => function (string $heroId): void {
                    $this->heroController()->show($heroId);
                },
            ],
        ];
    }

    /**
     * @return array<int, array{pattern: string, regex: bool, handler: callable}>
     */
    private function getPostRoutes(): array
    {
        return [
            [
                'pattern' => '/activity/albums',
                'regex' => false,
                'handler' => function (): void {
                    $this->activityController()->store(ActivityScope::ALBUMS);
                },
            ],
            [
                'pattern' => '/activity/comic',
                'regex' => false,
                'handler' => function (): void {
                    $this->activityController()->store(ActivityScope::COMIC);
                },
            ],
            [
                'pattern' => '#^/activity/heroes/([A-Za-z0-9\\-]+)$#',
                'regex' => true,
                'handler' => function (string $heroId): void {
                    $this->activityController()->store(ActivityScope::HEROES, $heroId);
                },
            ],
            [
                'pattern' => '/login',
                'regex' => false,
                'handler' => function (): void {
                    $this->authController()->login();
                },
            ],
            [
                'pattern' => '/logout',
                'regex' => false,
                'handler' => function (): void {
                    $this->authController()->logout();
                },
            ],
            [
                'pattern' => '/dev/tests/run',
                'regex' => false,
                'handler' => function (): void {
                    $this->devController()->runTests();
                },
            ],
            [
                'pattern' => '#^/albums/([A-Za-z0-9\\-]+)/cover$#',
                'regex' => true,
                'handler' => function (string $albumId): void {
                    $this->albumController()->uploadCover($albumId);
                },
            ],
            [
                'pattern' => '/admin/seed-all',
                'regex' => false,
                'handler' => function (): void {
                    $adminController = $this->adminController();
                    if ($adminController === null) {
                        JsonResponse::error('Servicio de seed no disponible.', 500);
                        return;
                    }

                    $adminController->seedAll();
                },
            ],
            [
                'pattern' => '/comics/generate',
                'regex' => false,
                'handler' => function (): void {
                    $this->comicController()->generate();
                },
            ],
            [
                'pattern' => '/albums',
                'regex' => false,
                'handler' => function (): void {
                    $this->albumController()->store();
                },
            ],
            [
                'pattern' => '#^/albums/([A-Za-z0-9\\-]+)/heroes$#',
                'regex' => true,
                'handler' => function (string $albumId): void {
                    $this->heroController()->store($albumId);
                },
            ],
            [
                'pattern' => '/api/rag/heroes',
                'regex' => false,
                'handler' => function (): void {
                    $this->ragProxyController()->forwardHeroesComparison();
                },
            ],
        ];
    }

    /**
     * @return array<int, array{pattern: string, regex: bool, handler: callable}>
     */
    private function getPutRoutes(): array
    {
        return [
            [
                'pattern' => '#^/albums/([A-Za-z0-9\\-]+)$#',
                'regex' => true,
                'handler' => function (string $albumId): void {
                    $this->albumController()->update($albumId);
                },
            ],
            [
                'pattern' => '#^/heroes/([A-Za-z0-9\\-]+)$#',
                'regex' => true,
                'handler' => function (string $heroId): void {
                    $this->heroController()->update($heroId);
                },
            ],
        ];
    }

    /**
     * @return array<int, array{pattern: string, regex: bool, handler: callable}>
     */
    private function getDeleteRoutes(): array
    {
        return [
            [
                'pattern' => '/activity/albums',
                'regex' => false,
                'handler' => function (): void {
                    $this->activityController()->clear(ActivityScope::ALBUMS);
                },
            ],
            [
                'pattern' => '/activity/comic',
                'regex' => false,
                'handler' => function (): void {
                    $this->activityController()->clear(ActivityScope::COMIC);
                },
            ],
            [
                'pattern' => '#^/activity/heroes/([A-Za-z0-9\\-]+)$#',
                'regex' => true,
                'handler' => function (string $heroId): void {
                    $this->activityController()->clear(ActivityScope::HEROES, $heroId);
                },
            ],
            [
                'pattern' => '#^/albums/([A-Za-z0-9\\-]+)$#',
                'regex' => true,
                'handler' => function (string $albumId): void {
                    $this->albumController()->destroy($albumId);
                },
            ],
            [
                'pattern' => '/notifications',
                'regex' => false,
                'handler' => function (): void {
                    $this->notificationController()->clear();
                },
            ],
            [
                'pattern' => '#^/heroes/([A-Za-z0-9\\-]+)$#',
                'regex' => true,
                'handler' => function (string $heroId): void {
                    $this->heroController()->destroy($heroId);
                },
            ],
        ];
    }

    /**
     * @param array<int, array{pattern: string, regex: bool, handler: callable}> $routes
     */
    private function dispatchRoutes(string $path, array $routes): bool
    {
        foreach ($routes as $route) {
            $pattern = $route['pattern'];
            $handler = $route['handler'];

            if ($route['regex']) {
                $matches = [];
                if (preg_match($pattern, $path, $matches) === 1) {
                    $handler(...array_slice($matches, 1));
                    return true;
                }
                continue;
            }

            if ($path === $pattern) {
                $handler();
                return true;
            }
        }

        return false;
    }

    private ?AuthController $authController = null;
    private ?AuthMiddleware $authMiddleware = null;

    /**
     * @throws RuntimeException
     */
    private function authController(): AuthController
    {
        if ($this->authController === null) {
            $security = $this->security();
            $authService = $security['auth'] ?? null;
            $csrfManager = $security['csrf'] ?? null;
            $ipBlocker = $security['ipBlocker'] ?? null;

            if (!$authService instanceof AuthService || !$csrfManager instanceof CsrfTokenManager || !$ipBlocker instanceof IpBlockerService) {
                throw new RuntimeException('Servicios de autenticación no disponibles.');
            }

            $this->authController = new AuthController($authService, $csrfManager, $ipBlocker);
        }

        return $this->authController;
    }

    private function authMiddleware(): ?AuthMiddleware
    {
        if ($this->authMiddleware === null) {
            $security = $this->security();
            $middleware = $security['middleware'] ?? null;
            if ($middleware instanceof AuthMiddleware) {
                $this->authMiddleware = $middleware;
            }
        }

        return $this->authMiddleware;
    }

    private ?ActivityController $activityController = null;
    private ?AlbumController $albumController = null;
    private ?ReadmeController $readmeController = null;

    /**
     * @throws RuntimeException
     */
    private function activityController(): ActivityController
    {
        if ($this->activityController === null) {
            $useCases = $this->useCases();

            $listActivity = $useCases['listActivity'] ?? null;
            $recordActivity = $useCases['recordActivity'] ?? null;
            $clearActivity = $useCases['clearActivity'] ?? null;

            if (!$listActivity instanceof ListActivityLogUseCase
                || !$recordActivity instanceof RecordActivityUseCase
                || !$clearActivity instanceof ClearActivityLogUseCase) {
                throw new RuntimeException('Casos de uso de actividad no disponibles.');
            }

            $this->activityController = new ActivityController(
                $listActivity,
                $recordActivity,
                $clearActivity,
            );
        }

        return $this->activityController;
    }

    private function albumController(): AlbumController
    {
        if ($this->albumController === null) {
            $useCases = $this->useCases();

            $this->albumController = new AlbumController(
                $useCases['listAlbums'],
                $useCases['createAlbum'],
                $useCases['updateAlbum'],
                $useCases['deleteAlbum'],
                $useCases['findAlbum'],
                $useCases['uploadAlbumCover'],
            );
        }

        return $this->albumController;
    }

    /**
     * @throws RuntimeException
     */
    private function readmeController(): ReadmeController
    {
        if ($this->readmeController === null) {
            $controller = $this->container['readme.show'] ?? null;

            if (is_callable($controller)) {
                $controller = $controller();
            }

            if (!$controller instanceof ReadmeController) {
                throw new RuntimeException('Controlador de README no disponible.');
            }

            $this->readmeController = $controller;
        }

        return $this->readmeController;
    }

    private ?HeroController $heroController = null;

    private function heroController(): HeroController
    {
        if ($this->heroController === null) {
            $useCases = $this->useCases();

            $this->heroController = new HeroController(
                $useCases['listHeroes'],
                $useCases['createHero'],
                $useCases['updateHero'],
                $useCases['deleteHero'],
                $useCases['findHero'],
            );
        }

        return $this->heroController;
    }

    private ?NotificationController $notificationController = null;

    private function notificationController(): NotificationController
    {
        if ($this->notificationController === null) {
            $useCases = $this->useCases();

            $this->notificationController = new NotificationController(
                $useCases['listNotifications'],
                $useCases['clearNotifications'],
            );
        }

        return $this->notificationController;
    }

    private ?ComicController $comicController = null;

    private function comicController(): ComicController
    {
        if ($this->comicController === null) {
            $useCases = $this->useCases();
            $this->comicController = new ComicController($useCases['generateComic']);
        }

        return $this->comicController;
    }

    private ?DevController $devController = null;

    private function devController(): DevController
    {
        if ($this->devController === null) {
            $testRunner = $this->container['devTools']['testRunner'] ?? null;
            if (!$testRunner instanceof PhpUnitTestRunner) {
                $testRunner = PhpUnitTestRunner::fromEnvironment(dirname(__DIR__, 3));
            }

            $this->devController = new DevController($testRunner);
        }

        return $this->devController;
    }

    private ?AdminController $adminController = null;
    private bool $adminControllerInitialized = false;

    private function adminController(): ?AdminController
    {
        if (!$this->adminControllerInitialized) {
            $seedService = $this->container['seedHeroesService'] ?? null;
            if ($seedService instanceof SeedHeroesService) {
                $this->adminController = new AdminController($seedService);
            }
            $this->adminControllerInitialized = true;
        }

        return $this->adminController;
    }

    private ?PageController $pageController = null;

    private ?RagProxyController $ragProxyController = null;

    private function ragProxyController(): RagProxyController
    {
        if ($this->ragProxyController === null) {
            $provider = $this->serviceUrlProvider();
            $ragUrl = $provider->getRagHeroesUrl();
            
            $security = $this->security();
            $internalKey = $security['internal_api_key'] ?? null;

            $this->ragProxyController = new RagProxyController(
                new \App\Shared\Infrastructure\Http\CurlHttpClient(),
                $ragUrl,
                is_string($internalKey) ? $internalKey : null
            );
        }

        return $this->ragProxyController;
    }

    private ?ConfigController $configController = null;

    private function configController(): ConfigController
    {
        if ($this->configController === null) {
            $provider = $this->serviceUrlProvider();
            $this->configController = new ConfigController($provider);
        }

        return $this->configController;
    }

    private function serviceUrlProvider(): ServiceUrlProvider
    {
        $provider = $this->container['services']['urlProvider'] ?? null;
        if (!$provider instanceof ServiceUrlProvider) {
            $config = $this->container['config']['services'] ?? [];
            $provider = new ServiceUrlProvider(is_array($config) ? $config : []);
        }
        return $provider;
    }

    private function pageController(): PageController
    {
        if ($this->pageController === null) {
            $this->pageController = new PageController();
        }

        return $this->pageController;
    }

    private ?HealthCheckController $healthCheckController = null;

    private function healthCheckController(): HealthCheckController
    {
        if ($this->healthCheckController === null) {
            $provider = $this->serviceUrlProvider();
            $environment = $this->container['config']['environment'] ?? 'production';
            $this->healthCheckController = new HealthCheckController(
                new \App\Shared\Infrastructure\Http\CurlHttpClient(),
                $provider,
                is_string($environment) ? $environment : 'production'
            );
        }

        return $this->healthCheckController;
    }

    private ?RateLimitMiddleware $rateLimitMiddleware = null;
    private ?ApiFirewall $apiFirewall = null;

    private function rateLimitMiddleware(): ?RateLimitMiddleware
    {
        if ($this->rateLimitMiddleware === null) {
            $security = $this->security();
            $middleware = $security['rateLimitMiddleware'] ?? null;
            if ($middleware instanceof RateLimitMiddleware) {
                $this->rateLimitMiddleware = $middleware;
            }
        }

        return $this->rateLimitMiddleware;
    }

    private function apiFirewall(): ?ApiFirewall
    {
        if ($this->apiFirewall === null) {
            $security = $this->security();
            $firewall = $security['apiFirewall'] ?? null;
            if ($firewall instanceof ApiFirewall) {
                $this->apiFirewall = $firewall;
            }
        }

        return $this->apiFirewall;
    }
}
