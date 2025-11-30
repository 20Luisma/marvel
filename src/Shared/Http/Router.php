<?php

declare(strict_types=1);

namespace Src\Shared\Http;

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
use Src\Controllers\ActivityController;
use Src\Controllers\AdminController;
use Src\Controllers\AlbumController;
use Src\Controllers\AuthController;
use Src\Controllers\ConfigController;
use Src\Controllers\ComicController;
use Src\Controllers\DevController;
use Src\Controllers\HeroController;
use Src\Controllers\Http\Request;
use Src\Controllers\NotificationController;
use Src\Controllers\PageController;
use Src\Controllers\RagProxyController;
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
            JsonResponse::error('Error inesperado: ' . $exception->getMessage(), 500);
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
        if ($path === '/activity/albums') {
            $this->activityController()->index(ActivityScope::ALBUMS);
            return true;
        }

        if ($path === '/activity/comic') {
            $this->activityController()->index(ActivityScope::COMIC);
            return true;
        }

        if (preg_match('#^/activity/heroes/([A-Za-z0-9\-]+)$#', $path, $matches) === 1) {
            $this->activityController()->index(ActivityScope::HEROES, $matches[1]);
            return true;
        }

        if ($path === '/albums') {
            $this->albumController()->index();
            return true;
        }

        if ($path === '/readme/raw') {
            ($this->readmeController())();
            return true;
        }

        if ($path === '/config/services') {
            $this->configController()->services();
            return true;
        }

        if ($path === '/heroes') {
            $this->heroController()->index();
            return true;
        }

        if (preg_match('#^/albums/([A-Za-z0-9\-]+)/heroes$#', $path, $matches) === 1) {
            $this->heroController()->listByAlbum($matches[1]);
            return true;
        }

        if ($path === '/notifications') {
            $this->notificationController()->index();
            return true;
        }

        if (preg_match('#^/heroes/([A-Za-z0-9\-]+)$#', $path, $matches) === 1) {
            $this->heroController()->show($matches[1]);
            return true;
        }

        return false;
    }

    private function handlePost(string $path): bool
    {
        if ($path === '/activity/albums') {
            $this->activityController()->store(ActivityScope::ALBUMS);
            return true;
        }

        if ($path === '/activity/comic') {
            $this->activityController()->store(ActivityScope::COMIC);
            return true;
        }

        if (preg_match('#^/activity/heroes/([A-Za-z0-9\-]+)$#', $path, $matches) === 1) {
            $this->activityController()->store(ActivityScope::HEROES, $matches[1]);
            return true;
        }

        if ($path === '/login') {
            $this->authController()->login();
            return true;
        }

        if ($path === '/logout') {
            $this->authController()->logout();
            return true;
        }

        if ($path === '/dev/tests/run') {
            $this->devController()->runTests();
            return true;
        }

        if (preg_match('#^/albums/([A-Za-z0-9\-]+)/cover$#', $path, $matches) === 1) {
            $this->albumController()->uploadCover($matches[1]);
            return true;
        }

        if ($path === '/admin/seed-all') {
            $adminController = $this->adminController();
            if ($adminController === null) {
                JsonResponse::error('Servicio de seed no disponible.', 500);
                return true;
            }

            $adminController->seedAll();
            return true;
        }

        if ($path === '/comics/generate') {
            $this->comicController()->generate();
            return true;
        }

        if ($path === '/albums') {
            $this->albumController()->store();
            return true;
        }

        if (preg_match('#^/albums/([A-Za-z0-9\-]+)/heroes$#', $path, $matches) === 1) {
            $this->heroController()->store($matches[1]);
            return true;
        }

        if ($path === '/api/rag/heroes') {
            $this->ragProxyController()->forwardHeroesComparison();
            return true;
        }

        return false;
    }

    private function handlePut(string $path): bool
    {
        if (preg_match('#^/albums/([A-Za-z0-9\-]+)$#', $path, $matches) === 1) {
            $this->albumController()->update($matches[1]);
            return true;
        }

        if (preg_match('#^/heroes/([A-Za-z0-9\-]+)$#', $path, $matches) === 1) {
            $this->heroController()->update($matches[1]);
            return true;
        }

        return false;
    }

    private function handleDelete(string $path): bool
    {
        if ($path === '/activity/albums') {
            $this->activityController()->clear(ActivityScope::ALBUMS);
            return true;
        }

        if ($path === '/activity/comic') {
            $this->activityController()->clear(ActivityScope::COMIC);
            return true;
        }

        if (preg_match('#^/activity/heroes/([A-Za-z0-9\-]+)$#', $path, $matches) === 1) {
            $this->activityController()->clear(ActivityScope::HEROES, $matches[1]);
            return true;
        }

        if (preg_match('#^/albums/([A-Za-z0-9\-]+)$#', $path, $matches) === 1) {
            $this->albumController()->destroy($matches[1]);
            return true;
        }

        if ($path === '/notifications') {
            $this->notificationController()->clear();
            return true;
        }

        if (preg_match('#^/heroes/([A-Za-z0-9\-]+)$#', $path, $matches) === 1) {
            $this->heroController()->destroy($matches[1]);
            return true;
        }

        return false;
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

    private ?AuthController $authController = null;
    private ?AuthMiddleware $authMiddleware = null;

    private function authController(): AuthController
    {
        if ($this->authController === null) {
            $security = $this->container['security'] ?? [];
            $authService = is_array($security) ? ($security['auth'] ?? null) : null;
            $csrfManager = is_array($security) ? ($security['csrf'] ?? null) : null;
            $ipBlocker = is_array($security) ? ($security['ipBlocker'] ?? null) : null;

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
            $security = $this->container['security'] ?? [];
            $middleware = is_array($security) ? ($security['middleware'] ?? null) : null;
            if ($middleware instanceof AuthMiddleware) {
                $this->authMiddleware = $middleware;
            }
        }

        return $this->authMiddleware;
    }

    private ?ActivityController $activityController = null;
    private ?AlbumController $albumController = null;
    private ?ReadmeController $readmeController = null;

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
            );
        }

        return $this->albumController;
    }

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

            $generator = $this->container['ai']['comicGenerator'] ?? null;
            if (!$generator instanceof OpenAIComicGenerator) {
                $generator = new OpenAIComicGenerator();
            }

            $this->comicController = new ComicController($generator, $useCases['findHero']);
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
            
            $security = $this->container['security'] ?? [];
            $internalKey = is_array($security) ? ($security['internal_api_key'] ?? null) : null;

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

    private ?RateLimitMiddleware $rateLimitMiddleware = null;
    private ?ApiFirewall $apiFirewall = null;

    private function rateLimitMiddleware(): ?RateLimitMiddleware
    {
        if ($this->rateLimitMiddleware === null) {
            $security = $this->container['security'] ?? [];
            $middleware = is_array($security) ? ($security['rateLimitMiddleware'] ?? null) : null;
            if ($middleware instanceof RateLimitMiddleware) {
                $this->rateLimitMiddleware = $middleware;
            }
        }

        return $this->rateLimitMiddleware;
    }

    private function apiFirewall(): ?ApiFirewall
    {
        if ($this->apiFirewall === null) {
            $security = $this->container['security'] ?? [];
            $firewall = is_array($security) ? ($security['apiFirewall'] ?? null) : null;
            if ($firewall instanceof ApiFirewall) {
                $this->apiFirewall = $firewall;
            }
        }

        return $this->apiFirewall;
    }
}
