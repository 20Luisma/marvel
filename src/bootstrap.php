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
use App\Application\Security\IpBlockerService;
use App\Application\Security\LoginAttemptService;
use App\Security\Auth\AuthService;
use App\Security\Config\ConfigValidator;
use App\Security\Http\AuthMiddleware;
use App\Security\Http\CsrfTokenManager;
use App\Security\Http\SecurityHeaders;
use App\Security\Http\RateLimitMiddleware;
use App\Security\Http\ApiFirewall;
use App\Security\RateLimit\RateLimiter;
use App\Security\Logging\SecurityLogger;
use App\Security\Session\SessionReplayMonitor;
use App\Shared\Infrastructure\Bus\InMemoryEventBus;
use App\Shared\Infrastructure\Http\CurlHttpClient;
use App\Shared\Infrastructure\Persistence\PdoConnectionFactory;
use App\Monitoring\TraceIdGenerator;
use Sentry\ClientBuilder;
use Sentry\SentrySdk;
use Sentry\State\Hub;
use Src\Shared\Http\ReadmeController;

return (static function (): array {
    $rootPath = dirname(__DIR__);
    $envPath = $rootPath . DIRECTORY_SEPARATOR . '.env';

    $skipDotEnv = (getenv('APP_ENV') === 'test') || (($_ENV['APP_ENV'] ?? null) === 'test');
    if (!$skipDotEnv && is_file($envPath)) {
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

    // Trace ID global por request (se reutiliza en logs y cabeceras).
    $traceGenerator = new TraceIdGenerator();
    $traceId = $_SERVER['X_TRACE_ID'] ?? null;
    if (!is_string($traceId) || trim($traceId) === '') {
        $traceId = $traceGenerator->generate();
        $_SERVER['X_TRACE_ID'] = $traceId;
    }
    header('X-Trace-Id: ' . $traceId);

    if (session_status() === PHP_SESSION_NONE) {
        $cookieParams = session_get_cookie_params();
        $isSecure = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1'))
            || ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443);

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $cookieParams['path'],
            'domain' => $cookieParams['domain'],
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    $ip = is_string($_SERVER['REMOTE_ADDR'] ?? null) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
    $uaRaw = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ua = is_string($uaRaw) ? preg_replace('/[\\x00-\\x1F\\x7F]/', '', $uaRaw) : '';
    $ua = is_string($ua) ? substr($ua, 0, 200) : '';
    $path = $_SERVER['REQUEST_URI'] ?? 'unknown';
    $securityLogPath = $rootPath . '/storage/logs/security.log';
    if (!is_dir(dirname($securityLogPath))) {
        @mkdir(dirname($securityLogPath), 0775, true);
    }

    // FASE 7.4 — Anti-Replay en modo Soft
    if (empty($_SESSION['session_replay_token'])) {
        $_SESSION['session_replay_token'] = bin2hex(random_bytes(32));
        error_log(
            "[" . date('Y-m-d H:i:s') . "] event=session_replay_token_issued trace_id={$traceId} ip={$ip} path={$path} user_agent={$ua} timestamp=" . time() . "\n",
            3,
            $securityLogPath
        );
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $header = $_SERVER['HTTP_X_SESSION_REPLAY'] ?? null;
        $expected = $_SESSION['session_replay_token'] ?? null;

        if (!$header) {
            error_log(
                "[" . date('Y-m-d H:i:s') . "] event=session_replay_missing_soft trace_id={$traceId} ip={$ip} path={$path} user_agent={$ua} timestamp=" . time() . "\n",
                3,
                $securityLogPath
            );
        } elseif (!is_string($expected) || $header !== $expected) {
            error_log(
                "[" . date('Y-m-d H:i:s') . "] event=session_replay_mismatch_soft trace_id={$traceId} ip={$ip} path={$path} user_agent={$ua} timestamp=" . time() . "\n",
                3,
                $securityLogPath
            );
        } else {
            error_log(
                "[" . date('Y-m-d H:i:s') . "] event=session_replay_valid_soft trace_id={$traceId} ip={$ip} path={$path} user_agent={$ua} timestamp=" . time() . "\n",
                3,
                $securityLogPath
            );
        }
    }

    // Middleware de cabeceras de seguridad para toda la app.
    // FASE 8.1 — CSP con nonces dinámicos
    // En test mode, los tests manejan SecurityHeaders manualmente para evitar contaminación
    $isTestEnv = ($appEnvironment === 'test');
    if (!$isTestEnv) {
        $cspNonce = \App\Security\Http\CspNonceGenerator::generate();
        $_SERVER['CSP_NONCE'] = $cspNonce; // Disponible para vistas
        SecurityHeaders::apply($cspNonce);
    }
    
    // Cabeceras adicionales de protección.
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
    // Persistencia adaptativa configurable por driver:
    // - Por defecto usamos DB para álbumes/héroes/actividad.
    // - En test o si el driver es "file", usamos JSON.
    // - Si falla la conexión PDO, caemos a JSON sin romper el arranque.
    $resolveDriver = static function (string $key, string $appEnv, string $default = 'db'): string {
        $raw = $_ENV[$key] ?? getenv($key);
        if (is_string($raw) && trim($raw) !== '') {
            $normalized = strtolower(trim($raw));
            return in_array($normalized, ['db', 'file'], true) ? $normalized : $default;
        }

        return $appEnv === 'test' ? 'file' : $default;
    };

    $albumDriver = $resolveDriver('ALBUMS_DRIVER', $appEnvironment);
    $heroDriver = $resolveDriver('HEROES_DRIVER', $appEnvironment);
    $activityDriver = $resolveDriver('ACTIVITY_DRIVER', $appEnvironment);

    $useDatabase = in_array('db', [$albumDriver, $heroDriver, $activityDriver], true);

    $pdo = null;

    if ($useDatabase) {
        try {
            $pdo = PdoConnectionFactory::fromEnvironment();
        } catch (Throwable $e) {
            error_log('Fallo al abrir conexión PDO, se usará JSON: ' . $e->getMessage());
            $pdo = null;
            if ($albumDriver === 'db') {
                $albumDriver = 'file';
            }
            if ($heroDriver === 'db') {
                $heroDriver = 'file';
            }
            if ($activityDriver === 'db') {
                $activityDriver = 'file';
            }
        }
    }

    $storagePath = $rootPath . '/storage';

    $albumRepository = ($albumDriver === 'db' && $pdo !== null)
        ? new DbAlbumRepository($pdo)
        : new FileAlbumRepository($storagePath . '/albums.json');

    $heroRepository = ($heroDriver === 'db' && $pdo !== null)
        ? new DbHeroRepository($pdo)
        : new FileHeroRepository($storagePath . '/heroes.json');

    $activityRepository = ($activityDriver === 'db' && $pdo !== null)
        ? new DbActivityLogRepository($pdo)
        : new FileActivityLogRepository($storagePath . '/actividad');
    // -------------------------------------------------------------------------

    $eventBus = new InMemoryEventBus();

    $notificationSender = new FileNotificationSender($rootPath . '/storage/notifications.log');
    $notificationRepository = new NotificationRepository($rootPath . '/storage/notifications.log');

    $notificationHandler = new HeroCreatedNotificationHandler($notificationSender);
    $albumUpdatedHandler = new AlbumUpdatedNotificationHandler($notificationSender);

    $eventBus->subscribe($notificationHandler);
    $eventBus->subscribe($albumUpdatedHandler);

    $createHeroUseCase = new CreateHeroUseCase($heroRepository, $albumRepository, $eventBus);

    $securityLogger = new SecurityLogger();
    $replayMonitor = new SessionReplayMonitor($securityLogger);
    $authService = new AuthService(config: null, logger: $securityLogger, replayMonitor: $replayMonitor);
    $authService->enforceSessionSecurity();
    $replayMonitor->detectReplayAttack();

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

    $csrfTokenManager = new CsrfTokenManager($appEnvironment);
    $envInternalKey = $_ENV['INTERNAL_API_KEY'] ?? getenv('INTERNAL_API_KEY');
    $internalApiKey = is_string($envInternalKey) ? trim($envInternalKey) : '';

    $rateLimitEnabledRaw = $_ENV['RATE_LIMIT_ENABLED'] ?? getenv('RATE_LIMIT_ENABLED');
    if (!is_string($rateLimitEnabledRaw) || $rateLimitEnabledRaw === '') {
        $rateLimitEnabledRaw = 'true';
    }
    $rateLimitEnabled = $rateLimitEnabledRaw !== 'false';

    $defaultMaxRaw = $_ENV['RATE_LIMIT_DEFAULT_MAX_REQUESTS'] ?? getenv('RATE_LIMIT_DEFAULT_MAX_REQUESTS');
    if (!is_numeric($defaultMaxRaw)) {
        $defaultMaxRaw = 60;
    }
    $defaultMax = (int) $defaultMaxRaw;

    $defaultWindowRaw = $_ENV['RATE_LIMIT_DEFAULT_WINDOW_SECONDS'] ?? getenv('RATE_LIMIT_DEFAULT_WINDOW_SECONDS');
    if (!is_numeric($defaultWindowRaw)) {
        $defaultWindowRaw = 60;
    }
    $defaultWindow = (int) $defaultWindowRaw;
    $routeLimits = [
        '/login' => ['max' => 10, 'window' => 60],
        '/api/rag/heroes' => ['max' => 20, 'window' => 60],
        '/agentia' => ['max' => 20, 'window' => 60],
    ];

    $rateLimiter = new RateLimiter(
        enabled: $rateLimitEnabled,
        defaultMaxRequests: $defaultMax > 0 ? $defaultMax : 60,
        defaultWindowSeconds: $defaultWindow > 0 ? $defaultWindow : 60,
        routeLimits: $routeLimits
    );

    $loginAttemptService = new LoginAttemptService($securityLogger);
    $ipBlockerService = new IpBlockerService($loginAttemptService, $securityLogger);

    $container['security'] = [
        'auth' => $authService,
        'csrf' => $csrfTokenManager,
        'middleware' => new AuthMiddleware($authService),
        'internal_api_key' => $internalApiKey !== '' ? $internalApiKey : null,
        'rateLimiter' => $rateLimiter,
        'rateLimitMiddleware' => new RateLimitMiddleware($rateLimiter, $routeLimits, $securityLogger),
        'apiFirewall' => new ApiFirewall($securityLogger),
        'logger' => $securityLogger,
        'ipBlocker' => $ipBlockerService,
        'loginAttemptService' => $loginAttemptService,
        'replayMonitor' => $replayMonitor,
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

    $container['monitoring'] = [
        'trace_id' => $traceId,
    ];

    try {
        $container['seedHeroesService']->seedIfEmpty();
    } catch (Throwable $e) {
        error_log('Hero seeding failed: ' . $e->getMessage());
    }

    $container['readme.show'] = static fn(): ReadmeController => new ReadmeController($rootPath);

    $GLOBALS['__clean_marvel_container'] = $container;
    $GLOBALS['__clean_marvel_trace_id'] = $traceId;

    return $container;
})();

/*
--------------------------------------------------------------
 FASE 7.4 — SISTEMA ANTI-REPLAY (MODO SOFT)
--------------------------------------------------------------

Este sistema protege contra ataques de repetición de solicitudes
sin romper compatibilidad con las rutas existentes ni requerir
nuevos encabezados obligatorios.

Características:
- No bloquea tráfico real.
- Cada sesión tiene un token único session_replay_token.
- POST sin header → solo registra evento.
- POST con header incorrecto → registra evento.
- POST con header correcto → registra evento.
- En login exitoso el token rota silenciosamente.
- Totalmente compatible con fases 7.1, 7.2 y 7.3.

Eventos generados en security.log:
- session_replay_token_issued
- session_replay_missing_soft
- session_replay_mismatch_soft
- session_replay_valid_soft
- session_replay_rotated_soft
*/
