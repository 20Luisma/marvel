<?php

declare(strict_types=1);

use App\AI\OpenAIComicGenerator;
use App\Config\ServiceUrlProvider;
use App\Activities\Application\UseCase\ClearActivityLogUseCase;
use App\Activities\Application\UseCase\ListActivityLogUseCase;
use App\Activities\Application\UseCase\RecordActivityUseCase;
use App\Activities\Infrastructure\Persistence\FileActivityLogRepository;
use App\Activities\Infrastructure\Persistence\DbActivityLogRepository;
use App\Albums\Application\UseCase\CreateAlbumUseCase;
use App\Albums\Application\UseCase\DeleteAlbumUseCase;
use App\Albums\Application\UseCase\FindAlbumUseCase;
use App\Albums\Application\UseCase\ListAlbumsUseCase;
use App\Albums\Application\UseCase\UpdateAlbumUseCase;
use App\Heroes\Application\UseCase\SeedAlbumHeroesUseCase;
use App\Albums\Infrastructure\Persistence\FileAlbumRepository;
use App\Albums\Infrastructure\Persistence\DbAlbumRepository;
use App\Dev\Seed\SeedHeroesService;
use App\Dev\Test\PhpUnitTestRunner;
use App\Heatmap\Infrastructure\HttpHeatmapApiClient;
use App\Heroes\Application\UseCase\CreateHeroUseCase;
use App\Heroes\Application\UseCase\DeleteHeroUseCase;
use App\Heroes\Application\UseCase\FindHeroUseCase;
use App\Heroes\Application\UseCase\ListHeroesUseCase;
use App\Heroes\Application\UseCase\UpdateHeroUseCase;
use App\Heroes\Infrastructure\Persistence\FileHeroRepository;
use App\Heroes\Infrastructure\Persistence\DbHeroRepository;
use App\Notifications\Application\AlbumUpdatedNotificationHandler;
use App\Notifications\Application\ClearNotificationsUseCase;
use App\Notifications\Application\ListNotificationsUseCase;
use App\Notifications\Application\HeroCreatedNotificationHandler;
use App\Notifications\Infrastructure\FileNotificationSender;
use App\Notifications\Infrastructure\NotificationRepository;
use App\Security\Auth\AuthService;
use App\Security\Config\ConfigValidator;
use App\Security\Http\AuthMiddleware;
use App\Security\Http\CsrfTokenManager;
use App\Security\Http\SecurityHeaders;
use App\Shared\Infrastructure\Bus\InMemoryEventBus;
use App\Shared\Infrastructure\Http\CurlHttpClient;
use App\Shared\Infrastructure\Persistence\PdoConnectionFactory;
use Sentry\ClientBuilder;
use Sentry\SentrySdk;
use Sentry\State\Hub;
use Src\Shared\Http\ReadmeController;

return (static function (): array {
    $rootPath = dirname(__DIR__);
    $envPath = $rootPath . DIRECTORY_SEPARATOR . '.env';

    if (is_file($envPath)) {
        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (str_starts_with($line, '#')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2) + [1 => '']);

            if ($key !== '') {
                $_ENV[$key] = $value;
                putenv($key . '=' . $value);
            }
        }
    }

    // --- Sentry ---------------------------------------------------------------
    $sentryDsn = $_ENV['SENTRY_DSN'] ?? getenv('SENTRY_DSN') ?: null;
    $appEnvironment = $_ENV['APP_ENV'] ?? (getenv('APP_ENV') ?: null);

    if ($appEnvironment === '' || $appEnvironment === null) {
        $appEnvironment = 'local';
    }

    if (session_status() === PHP_SESSION_NONE) {
        $cookieParams = session_get_cookie_params();
        $isSecure = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1'))
            || ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443);

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $cookieParams['path'] ?? '/',
            'domain' => $cookieParams['domain'] ?? '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    // Middleware de cabeceras de seguridad para toda la app.
    SecurityHeaders::apply();

    if ($sentryDsn) {
        try {
            $client = ClientBuilder::create([
                'dsn' => $sentryDsn,
                'environment' => $appEnvironment,
                'traces_sample_rate' => 0.2,
            ])->getClient();

            $hub = new Hub($client);
            SentrySdk::setCurrentHub($hub);

            set_error_handler(static function ($severity, $message, $file, $fileLine) {
                if (!(error_reporting() & $severity)) {
                    return false;
                }

                \Sentry\captureMessage(sprintf('%s in %s:%d', $message, $file, $fileLine));
                return false;
            });

            set_exception_handler(static function (\Throwable $exception): void {
                \Sentry\captureException($exception);
                throw $exception;
            });
        } catch (\Throwable $e) {
            error_log('Error inicializando Sentry: ' . $e->getMessage());
        }
    }
    // -------------------------------------------------------------------------

    $serviceConfigPath = $rootPath . '/config/services.php';
    /** @var array<string, mixed> $serviceConfig */
    $serviceConfig = is_file($serviceConfigPath) ? require_once $serviceConfigPath : ['environments' => []];

    $GLOBALS['__clean_marvel_service_config'] = $serviceConfig;

    $serviceUrlProvider = null;
    if (class_exists(ServiceUrlProvider::class)) {
        $serviceUrlProvider = new ServiceUrlProvider($serviceConfig);
    }

    // Validación temprana de configuración para evitar fallos en runtime por .env incompleto.
    (new ConfigValidator($_ENV + ['APP_ENV' => $appEnvironment], $serviceUrlProvider, $appEnvironment))->validate();

    $tmdbApiKey = trim((string) (getenv('TMDB_API_KEY') ?: ($_ENV['TMDB_API_KEY'] ?? '')));
    // TODO: usar TMDB_API_KEY en el endpoint de películas Marvel
    $heatmapBaseUrl = trim((string) (getenv('HEATMAP_API_BASE_URL') ?: ($_ENV['HEATMAP_API_BASE_URL'] ?? 'http://34.74.102.123:8080'))); // TODO mover a env obligatorio
    if ($heatmapBaseUrl === '') {
        $heatmapBaseUrl = 'http://34.74.102.123:8080';
    }
    $heatmapApiToken = trim((string) (getenv('HEATMAP_API_TOKEN') ?: ($_ENV['HEATMAP_API_TOKEN'] ?? '')));

    // --- JSON vs DB ----------------------------------------------------------
    // Persistencia adaptativa:
    // - En local (APP_ENV=local) siempre usamos los repositorios JSON en storage/.
    // - En hosting intentamos abrir PDO (MySQL) y usar Db*Repository.
    // - Si PDO falla (credenciales/servicio caído), registramos el error y
    //   volvemos a JSON para no romper el arranque.
    $useDatabase = ($appEnvironment === 'hosting');

    $pdo = null;

    if ($useDatabase) {
        try {
            $pdo = PdoConnectionFactory::fromEnvironment();
        } catch (Throwable $e) {
            error_log('Fallo al abrir conexión PDO, se usará JSON: ' . $e->getMessage());
            $useDatabase = false;
        }
    }

    if ($useDatabase && $pdo !== null) {
        $albumRepository    = new DbAlbumRepository($pdo);
        $heroRepository     = new DbHeroRepository($pdo);
        $activityRepository = new DbActivityLogRepository($pdo);
    } else {
        $albumRepository    = new FileAlbumRepository($rootPath . '/storage/albums.json');
        $heroRepository     = new FileHeroRepository($rootPath . '/storage/heroes.json');
        $activityRepository = new FileActivityLogRepository($rootPath . '/storage/actividad');
    }
    // -------------------------------------------------------------------------

    $eventBus = new InMemoryEventBus();

    $notificationSender = new FileNotificationSender($rootPath . '/storage/notifications.log');
    $notificationRepository = new NotificationRepository($rootPath . '/storage/notifications.log');

    $notificationHandler = new HeroCreatedNotificationHandler($notificationSender);
    $albumUpdatedHandler = new AlbumUpdatedNotificationHandler($notificationSender);

    $eventBus->subscribe($notificationHandler);
    $eventBus->subscribe($albumUpdatedHandler);

    $createHeroUseCase = new CreateHeroUseCase($heroRepository, $albumRepository, $eventBus);

    $container = [
        'albumRepository'      => $albumRepository,
        'heroRepository'       => $heroRepository,
        'eventBus'             => $eventBus,
        'notificationRepository' => $notificationRepository,
        'activityRepository'   => $activityRepository,
        'config' => [
            'services' => $serviceConfig,
            'tmdbApiKey' => $tmdbApiKey,
        ],
        'services' => [
            'urlProvider' => $serviceUrlProvider,
            // Asegúrate de configurar HEATMAP_API_TOKEN tanto en PHP como en el microservicio Python.
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

    $authService = new AuthService();
    $csrfTokenManager = new CsrfTokenManager($appEnvironment);
    $internalApiKey = trim((string) ($_ENV['INTERNAL_API_KEY'] ?? getenv('INTERNAL_API_KEY') ?? ''));

    $container['security'] = [
        'auth' => $authService,
        'csrf' => $csrfTokenManager,
        'middleware' => new AuthMiddleware($authService),
        'internal_api_key' => $internalApiKey !== '' ? $internalApiKey : null,
    ];

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

    try {
        $container['seedHeroesService']->seedIfEmpty();
    } catch (Throwable $e) {
        error_log('Hero seeding failed: ' . $e->getMessage());
    }

    $container['readme.show'] = static fn(): ReadmeController => new ReadmeController($rootPath);

    $GLOBALS['__clean_marvel_container'] = $container;

    return $container;
})();
