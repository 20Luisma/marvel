<?php

declare(strict_types=1);

use App\AI\OpenAIComicGenerator;
use App\Config\ServiceUrlProvider;
use App\Activities\Application\UseCase\ClearActivityLogUseCase;
use App\Activities\Application\UseCase\ListActivityLogUseCase;
use App\Activities\Application\UseCase\RecordActivityUseCase;
use App\Activities\Infrastructure\Persistence\FileActivityLogRepository;
use App\Albums\Application\UseCase\CreateAlbumUseCase;
use App\Albums\Application\UseCase\DeleteAlbumUseCase;
use App\Albums\Application\UseCase\FindAlbumUseCase;
use App\Albums\Application\UseCase\ListAlbumsUseCase;
use App\Albums\Application\UseCase\UpdateAlbumUseCase;
use App\Heroes\Application\UseCase\SeedAlbumHeroesUseCase;
use App\Albums\Infrastructure\Persistence\FileAlbumRepository;
use App\Dev\Seed\SeedHeroesService;
use App\Dev\Test\PhpUnitTestRunner;
use App\Heroes\Application\UseCase\CreateHeroUseCase;
use App\Heroes\Application\UseCase\DeleteHeroUseCase;
use App\Heroes\Application\UseCase\FindHeroUseCase;
use App\Heroes\Application\UseCase\ListHeroesUseCase;
use App\Heroes\Application\UseCase\UpdateHeroUseCase;
use App\Heroes\Infrastructure\Persistence\FileHeroRepository;
use App\Notifications\Application\AlbumUpdatedNotificationHandler;
use App\Notifications\Application\ClearNotificationsUseCase;
use App\Notifications\Application\HeroCreatedNotificationHandler;
use App\Notifications\Application\ListNotificationsUseCase;
use App\Notifications\Infrastructure\FileNotificationSender;
use App\Notifications\Infrastructure\NotificationRepository;
use App\Shared\Infrastructure\Bus\InMemoryEventBus;
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

    $serviceConfigPath = $rootPath . '/config/services.php';
    /** @var array<string, mixed> $serviceConfig */
    $serviceConfig = is_file($serviceConfigPath) ? require $serviceConfigPath : ['environments' => []];
    $serviceUrlProvider = new ServiceUrlProvider($serviceConfig);

    $albumRepository = new FileAlbumRepository($rootPath . '/storage/albums.json');
    $heroRepository = new FileHeroRepository($rootPath . '/storage/heroes.json');
    $activityRepository = new FileActivityLogRepository($rootPath . '/storage/actividad');

    $eventBus = new InMemoryEventBus();

    $notificationSender = new FileNotificationSender($rootPath . '/storage/notifications.log');
    $notificationRepository = new NotificationRepository($rootPath . '/storage/notifications.log');

    $notificationHandler = new HeroCreatedNotificationHandler($notificationSender);
    $albumUpdatedHandler = new AlbumUpdatedNotificationHandler($notificationSender);

    $eventBus->subscribe($notificationHandler);
    $eventBus->subscribe($albumUpdatedHandler);

    $createHeroUseCase = new CreateHeroUseCase($heroRepository, $albumRepository, $eventBus);

    $container = [
        'albumRepository' => $albumRepository,
        'heroRepository' => $heroRepository,
        'eventBus' => $eventBus,
        'notificationRepository' => $notificationRepository,
        'activityRepository' => $activityRepository,
        'config' => [
            'services' => $serviceConfig,
        ],
        'services' => [
            'urlProvider' => $serviceUrlProvider,
        ],
        'useCases' => [
            'createAlbum' => new CreateAlbumUseCase($albumRepository),
            'seedAlbumHeroes' => new SeedAlbumHeroesUseCase($albumRepository, $heroRepository, $eventBus),
            'updateAlbum' => new UpdateAlbumUseCase($albumRepository, $eventBus),
            'listAlbums' => new ListAlbumsUseCase($albumRepository),
            'deleteAlbum' => new DeleteAlbumUseCase($albumRepository, $heroRepository),
            'findAlbum' => new FindAlbumUseCase($albumRepository),
            'createHero' => $createHeroUseCase,
            'listHeroes' => new ListHeroesUseCase($heroRepository),
            'findHero' => new FindHeroUseCase($heroRepository),
            'deleteHero' => new DeleteHeroUseCase($heroRepository),
            'updateHero' => new UpdateHeroUseCase($heroRepository),
            'clearNotifications' => new ClearNotificationsUseCase($notificationRepository),
            'listNotifications' => new ListNotificationsUseCase($notificationRepository),
            'recordActivity' => new RecordActivityUseCase($activityRepository),
            'listActivity' => new ListActivityLogUseCase($activityRepository),
            'clearActivity' => new ClearActivityLogUseCase($activityRepository),
        ],
    ];

    $container['seedHeroesService'] = new SeedHeroesService(
        $albumRepository,
        $heroRepository,
        $createHeroUseCase
    );

    $openAiServiceUrl = $_ENV['OPENAI_SERVICE_URL'] ?? getenv('OPENAI_SERVICE_URL') ?: null;
    if (!is_string($openAiServiceUrl) || trim($openAiServiceUrl) === '') {
        $openAiServiceUrl = $serviceUrlProvider->getOpenAiChatUrl();
    }

    $container['ai'] = [
        'comicGenerator' => new OpenAIComicGenerator($openAiServiceUrl),
    ];

    $container['devTools'] = [
        'testRunner' => PhpUnitTestRunner::fromEnvironment($rootPath),
    ];

    try {
        $container['seedHeroesService']->seedIfEmpty();
    } catch (Throwable $e) {
        // Do not break the app on boot if seeding fails
        error_log('Hero seeding failed: ' . $e->getMessage());
    }

    $container['readme.show'] = static fn (): ReadmeController => new ReadmeController($rootPath);

    return $container;
})();
