<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\AI\OpenAIComicGenerator;
use App\Activities\Application\UseCase\ClearActivityLogUseCase;
use App\Activities\Application\UseCase\ListActivityLogUseCase;
use App\Activities\Application\UseCase\RecordActivityUseCase;
use App\Activities\Infrastructure\Persistence\DbActivityLogRepository;
use App\Activities\Infrastructure\Persistence\FileActivityLogRepository;
use App\Albums\Application\UseCase\CreateAlbumUseCase;
use App\Albums\Application\UseCase\DeleteAlbumUseCase;
use App\Albums\Application\UseCase\FindAlbumUseCase;
use App\Albums\Application\UseCase\ListAlbumsUseCase;
use App\Albums\Application\UseCase\UpdateAlbumUseCase;
use App\Albums\Domain\Repository\AlbumRepository;
use App\Config\ServiceUrlProvider;
use App\Dev\Seed\SeedHeroesService;
use App\Dev\Test\PhpUnitTestRunner;
use App\Heatmap\Infrastructure\HttpHeatmapApiClient;
use App\Heroes\Domain\Repository\HeroRepository;
use App\Heroes\Application\UseCase\CreateHeroUseCase;
use App\Heroes\Application\UseCase\DeleteHeroUseCase;
use App\Heroes\Application\UseCase\FindHeroUseCase;
use App\Heroes\Application\UseCase\ListHeroesUseCase;
use App\Heroes\Application\UseCase\SeedAlbumHeroesUseCase;
use App\Heroes\Application\UseCase\UpdateHeroUseCase;
use App\Notifications\Application\ClearNotificationsUseCase;
use App\Notifications\Application\ListNotificationsUseCase;
use App\Notifications\Infrastructure\NotificationRepository;
use App\Security\Config\ConfigValidator;
use App\Security\Http\SecurityHeaders;
use App\Shared\Http\ReadmeController;
use App\Shared\Infrastructure\Bus\InMemoryEventBus;
use App\Shared\Infrastructure\Http\CurlHttpClient;
use App\Bootstrap\Shared\DriverResolver;
use PDO;
use Throwable;

final class AppBootstrap
{
    /**
     * @return array<string, mixed>
     */
    public static function init(): array
    {
        $rootPath = dirname(__DIR__, 2);

        EnvironmentBootstrap::initialize();

        $appEnvironment = $_ENV['APP_ENV'] ?? (getenv('APP_ENV') ?: null);
        if ($appEnvironment === '' || $appEnvironment === null) {
            $appEnvironment = 'local';
        }

        $traceId = $_SERVER['X_TRACE_ID'] ?? '';

        // Seguridad de cabeceras (se mantiene comportamiento original).
        $isTestEnv = ($appEnvironment === 'test');
        if (!$isTestEnv) {
            $cspNonce = \App\Security\Http\CspNonceGenerator::generate();
            $_SERVER['CSP_NONCE'] = $cspNonce;
            SecurityHeaders::apply($cspNonce);
        }

        if ($isTestEnv && !isset($GLOBALS['__test_headers'])) {
            $GLOBALS['__test_headers'] = [];
        }
        $testHeaders =& $GLOBALS['__test_headers'];
        $addHeader = static function (string $name, string $value) use (&$testHeaders, $isTestEnv): void {
            header($name . ': ' . $value);
            if ($isTestEnv) {
                $testHeaders[] = $name . ': ' . $value;
            }
        };

        $addHeader('X-Content-Security-Policy', $_SERVER['HTTP_CONTENT_SECURITY_POLICY'] ?? "default-src 'self'");
        $addHeader('X-XSS-Protection', '0');
        $addHeader('Cross-Origin-Resource-Policy', 'same-origin');
        $addHeader('Cross-Origin-Opener-Policy', 'same-origin');
        $addHeader('Cross-Origin-Embedder-Policy', 'unsafe-none');

        $serviceConfigPath = $rootPath . '/config/services.php';
        $serviceConfig = $GLOBALS['__clean_marvel_service_config'] ?? null;
        if (!is_array($serviceConfig)) {
            /** @var array<string, mixed> $serviceConfig */
            $serviceConfig = is_file($serviceConfigPath) ? require_once $serviceConfigPath : ['environments' => []];
            $GLOBALS['__clean_marvel_service_config'] = $serviceConfig;
        }

        $serviceUrlProvider = null;
        if (class_exists(ServiceUrlProvider::class)) {
            $serviceUrlProvider = new ServiceUrlProvider($serviceConfig);
        }

        (new ConfigValidator($_ENV + ['APP_ENV' => $appEnvironment], $serviceUrlProvider, $appEnvironment))->validate();

        $tmdbApiKey = trim((string) (getenv('TMDB_API_KEY') ?: ($_ENV['TMDB_API_KEY'] ?? '')));
        $heatmapBaseUrl = trim((string) (getenv('HEATMAP_API_BASE_URL') ?: ($_ENV['HEATMAP_API_BASE_URL'] ?? 'http://34.74.102.123:8080')));
        if ($heatmapBaseUrl === '') {
            $heatmapBaseUrl = 'http://34.74.102.123:8080';
        }
        $heatmapApiToken = trim((string) (getenv('HEATMAP_API_TOKEN') ?: ($_ENV['HEATMAP_API_TOKEN'] ?? '')));

        /** @var array{albumRepository: AlbumRepository, heroRepository: HeroRepository, pdo: PDO|null} $persistence */
        $persistence = PersistenceBootstrap::initialize($appEnvironment);

        $activityDriver = DriverResolver::resolve('ACTIVITY_DRIVER', $appEnvironment);
        if (defined('PHPUNIT_RUNNING')) {
            $activityDriver = 'file';
        }

        $pdo = $persistence['pdo'] ?? null;
        $storagePath = $rootPath . '/storage';

        $activityRepository = ($activityDriver === 'db' && $pdo !== null)
            ? new DbActivityLogRepository($pdo)
            : new FileActivityLogRepository($storagePath . '/actividad');

        /** @var array{eventBus: InMemoryEventBus, notificationSender: object, notificationRepository: NotificationRepository} $events */
        $events = EventBootstrap::initialize($rootPath);
        $security = SecurityBootstrap::initialize($appEnvironment);
        $observability = ObservabilityBootstrap::initialize($appEnvironment, is_string($traceId) ? $traceId : '');

        /** @var InMemoryEventBus $eventBus */
        $eventBus = $events['eventBus'];
        /** @var NotificationRepository $notificationRepository */
        $notificationRepository = $events['notificationRepository'];
        $notificationSender = $events['notificationSender'];

        /** @var AlbumRepository $albumRepository */
        $albumRepository = $persistence['albumRepository'];
        /** @var HeroRepository $heroRepository */
        $heroRepository = $persistence['heroRepository'];
        /** @var PDO|null $pdo */
        $pdo = $persistence['pdo'] ?? null;

        $createHeroUseCase = new CreateHeroUseCase($heroRepository, $albumRepository, $eventBus);

        $container = [
            'albumRepository'      => $albumRepository,
            'heroRepository'       => $heroRepository,
            'pdo'                  => $pdo,
            'eventBus'             => $eventBus,
            'notificationSender'   => $notificationSender,
            'notificationRepository' => $notificationRepository,
            'activityRepository'   => $activityRepository,
            'config' => [
                'services' => $serviceConfig,
                'tmdbApiKey' => $tmdbApiKey,
            ],
            'services' => [
                'urlProvider' => $serviceUrlProvider,
                'heatmapApiClient' => new HttpHeatmapApiClient($heatmapBaseUrl, $heatmapApiToken !== '' ? $heatmapApiToken : null),
            ],
            'useCases' => [
                'createAlbum'    => new CreateAlbumUseCase($albumRepository),
                'seedAlbumHeroes' => new SeedAlbumHeroesUseCase($albumRepository, $heroRepository, $eventBus),
                'updateAlbum'    => new UpdateAlbumUseCase($albumRepository, $eventBus),
                'listAlbums'     => new ListAlbumsUseCase($albumRepository),
                'deleteAlbum'    => new DeleteAlbumUseCase($albumRepository, $heroRepository),
                'findAlbum'      => new FindAlbumUseCase($albumRepository),
                'createHero'     => $createHeroUseCase,
                'listHeroes'     => new ListHeroesUseCase($heroRepository),
                'findHero'       => new FindHeroUseCase($heroRepository),
                'deleteHero'     => new DeleteHeroUseCase($heroRepository),
                'updateHero'     => new UpdateHeroUseCase($heroRepository),
                'clearNotifications' => new ClearNotificationsUseCase($notificationRepository),
                'listNotifications'  => new ListNotificationsUseCase($notificationRepository),
                'recordActivity'     => new RecordActivityUseCase($activityRepository),
                'listActivity'       => new ListActivityLogUseCase($activityRepository),
                'clearActivity'      => new ClearActivityLogUseCase($activityRepository),
            ],
        ];

        $container = array_merge($container, $security);

        $container['seedHeroesService'] = new SeedHeroesService(
            $albumRepository,
            $heroRepository,
            $createHeroUseCase
        );

        $openAiServiceUrl = $_ENV['OPENAI_SERVICE_URL'] ?? getenv('OPENAI_SERVICE_URL') ?: null;

        if (!is_string($openAiServiceUrl) || trim($openAiServiceUrl) === '') {
            if ($serviceUrlProvider instanceof ServiceUrlProvider) {
                $openAiServiceUrl = $serviceUrlProvider->getOpenAiChatUrl();
            } else {
                $openAiServiceUrl = null;
            }
        }

        $container['ai'] = [
            'comicGenerator' => new OpenAIComicGenerator($openAiServiceUrl),
        ];

        $container['devTools'] = [
            'testRunner' => PhpUnitTestRunner::fromEnvironment($rootPath),
        ];

        $container['http'] = [
            'client' => new CurlHttpClient(),
        ];

        $container = array_merge($container, $observability);

        if (!isset($container['monitoring']['trace_id'])) {
            $container['monitoring']['trace_id'] = is_string($traceId) ? $traceId : '';
        }

        try {
            $container['seedHeroesService']->seedIfEmpty();
        } catch (Throwable $e) {
            error_log('Hero seeding failed: ' . $e->getMessage());
        }

        $container['readme.show'] = static fn(): ReadmeController => new ReadmeController($rootPath);

        $GLOBALS['__clean_marvel_container'] = $container;
        $GLOBALS['__clean_marvel_trace_id'] = is_string($traceId) ? $traceId : '';

        return $container;
    }
}
