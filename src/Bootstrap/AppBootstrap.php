<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\AI\OpenAIComicGenerator;
use App\Application\Comics\GenerateComicUseCase;
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
use App\Albums\Application\UseCase\UploadAlbumCoverUseCase;
use App\Shared\Infrastructure\Filesystem\LocalFilesystem;
use App\Albums\Domain\Repository\AlbumRepository;
use App\Config\ServiceUrlProvider;
use App\Dev\Seed\SeedHeroesService;
use App\Dev\Test\PhpUnitTestRunner;
use App\Heatmap\Infrastructure\HttpHeatmapApiClient;
use App\Heatmap\Infrastructure\ReplicatedHeatmapApiClient;
use App\Heroes\Application\Rag\HeroRagSyncer;
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
use App\Shared\Infrastructure\Security\InternalRequestSigner;
use App\Heroes\Infrastructure\Rag\HeroRagSyncService;
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

        $traceId = $_SERVER['X_TRACE_ID'] ?? '';

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

        // Resolver APP_ENV=auto en base al host/config para que los servicios (ej. sync RAG) no se queden apuntando a localhost en hosting.
        $declaredEnvironment = $_ENV['APP_ENV'] ?? (getenv('APP_ENV') ?: null);
        $appEnvironment = AppEnvironmentResolver::resolve(
            is_string($declaredEnvironment) ? $declaredEnvironment : null,
            $serviceUrlProvider,
            is_string($_SERVER['HTTP_HOST'] ?? null) ? (string) $_SERVER['HTTP_HOST'] : null
        );
        if (!is_string($declaredEnvironment) || trim($declaredEnvironment) === '' || strcasecmp(trim($declaredEnvironment), 'auto') === 0) {
            $_ENV['APP_ENV'] = $appEnvironment;
            putenv('APP_ENV=' . $appEnvironment);
        }

        (new ConfigValidator($_ENV + ['APP_ENV' => $appEnvironment], $serviceUrlProvider, $appEnvironment))->validate();

        // Seguridad de cabeceras.
        $isTestEnv = ($appEnvironment === 'test');
        if (!$isTestEnv) {
            $cspNonce = $_SERVER['CSP_NONCE'] ?? \App\Security\Http\CspNonceGenerator::generate();
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

        $tmdbApiKey = trim((string) (getenv('TMDB_API_KEY') ?: ($_ENV['TMDB_API_KEY'] ?? '')));
        $heatmapBaseUrl = trim((string) (getenv('HEATMAP_API_BASE_URL') ?: ($_ENV['HEATMAP_API_BASE_URL'] ?? 'http://34.74.102.123:8080')));
        $heatmapSecondaryUrl = trim((string) (getenv('HEATMAP_API_SECONDARY_URL') ?: ($_ENV['HEATMAP_API_SECONDARY_URL'] ?? '')));
        $heatmapApiToken = trim((string) (getenv('HEATMAP_API_TOKEN') ?: ($_ENV['HEATMAP_API_TOKEN'] ?? '')));
        $httpClient = new CurlHttpClient();

        /** @var array{albumRepository: AlbumRepository, heroRepository: HeroRepository, pdo: PDO|null} $persistence */
        $persistence = PersistenceBootstrap::initialize($appEnvironment);

        $activityDriver = DriverResolver::resolve('ACTIVITY_DRIVER', $appEnvironment);
        if (defined('PHPUNIT_RUNNING')) {
            $activityDriver = 'file';
        }

        $pdo = $persistence['pdo'] ?? null;
        $storagePath = $rootPath . '/storage';

        $internalApiKey = trim((string) (getenv('INTERNAL_API_KEY') ?: ($_ENV['INTERNAL_API_KEY'] ?? '')));
        $callerId = $serviceUrlProvider instanceof ServiceUrlProvider
            ? ($serviceUrlProvider->getAppHost($appEnvironment) ?: 'clean-marvel-app')
            : 'clean-marvel-app';
        $ragSigner = $internalApiKey !== '' ? new InternalRequestSigner($internalApiKey, $callerId) : null;
        $ragSyncLog = $storagePath . '/logs/rag_sync_client.log';
        $ragSyncer = new HeroRagSyncService($httpClient, $serviceUrlProvider, $ragSigner, $appEnvironment, $ragSyncLog);

        $activityRepository = ($activityDriver === 'db' && $pdo !== null)
            ? new DbActivityLogRepository($pdo)
            : new FileActivityLogRepository($storagePath . '/actividad');

        $events = EventBootstrap::initialize($rootPath);
        $security = SecurityBootstrap::initialize($appEnvironment);
        $observability = ObservabilityBootstrap::initialize($appEnvironment, is_string($traceId) ? $traceId : '');

        $openAiServiceUrl = $_ENV['OPENAI_SERVICE_URL'] ?? getenv('OPENAI_SERVICE_URL') ?: null;
        if (!is_string($openAiServiceUrl) || trim($openAiServiceUrl) === '') {
            if ($serviceUrlProvider instanceof ServiceUrlProvider) {
                $openAiServiceUrl = $serviceUrlProvider->getOpenAiChatUrl();
            } else {
                $openAiServiceUrl = null;
            }
        }

        $services = [
            'activityRepository' => $activityRepository,
            'ragSyncer' => $ragSyncer,
        ];

        $useCases = ServiceContainer::build(
            $persistence,
            $events,
            $services,
            $rootPath,
            $openAiServiceUrl
        );

        $container = [
            'albumRepository'      => $persistence['albumRepository'],
            'heroRepository'       => $persistence['heroRepository'],
            'pdo'                  => $persistence['pdo'],
            'eventBus'             => $events['eventBus'],
            'notificationSender'   => $events['notificationSender'],
            'notificationRepository' => $events['notificationRepository'],
            'activityRepository'   => $activityRepository,
            'config' => [
                'services' => $serviceConfig,
                'tmdbApiKey' => $tmdbApiKey,
                'environment' => $appEnvironment,
            ],
            'services' => [
                'urlProvider' => $serviceUrlProvider,
                'heatmapApiClient' => (function() use ($heatmapBaseUrl, $heatmapSecondaryUrl, $heatmapApiToken) {
                    $clients = [];
                    if ($heatmapBaseUrl !== '') {
                        $clients[] = new HttpHeatmapApiClient($heatmapBaseUrl, $heatmapApiToken !== '' ? $heatmapApiToken : null);
                    }
                    if ($heatmapSecondaryUrl !== '') {
                        $clients[] = new HttpHeatmapApiClient($heatmapSecondaryUrl, $heatmapApiToken !== '' ? $heatmapApiToken : null);
                    }
                    // ReplicatedHeatmapApiClient: escribe en TODOS los nodos simultáneamente.
                    // Si un nodo falla, encola el click y lo sincroniza cuando el nodo se recupera.
                    return new ReplicatedHeatmapApiClient(...$clients);
                })(),
                'ragSyncer' => $ragSyncer,
            ],
            'useCases' => $useCases,
        ];

        $container = array_merge($container, $security);
        $container = array_merge($container, $observability);

        $container['seedHeroesService'] = new SeedHeroesService(
            $container['albumRepository'],
            $container['heroRepository'],
            $container['useCases']['createHero']
        );

        $container['devTools'] = [
            'testRunner' => PhpUnitTestRunner::fromEnvironment($rootPath),
        ];

        $container['http'] = [
            'client' => $httpClient,
        ];

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
