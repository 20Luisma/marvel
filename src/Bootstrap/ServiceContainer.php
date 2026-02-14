<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Albums\Application\UseCase\CreateAlbumUseCase;
use App\Albums\Application\UseCase\DeleteAlbumUseCase;
use App\Albums\Application\UseCase\FindAlbumUseCase;
use App\Albums\Application\UseCase\ListAlbumsUseCase;
use App\Albums\Application\UseCase\UpdateAlbumUseCase;
use App\Albums\Application\UseCase\UploadAlbumCoverUseCase;
use App\Heroes\Application\UseCase\CreateHeroUseCase;
use App\Heroes\Application\UseCase\DeleteHeroUseCase;
use App\Heroes\Application\UseCase\FindHeroUseCase;
use App\Heroes\Application\UseCase\ListHeroesUseCase;
use App\Heroes\Application\UseCase\SeedAlbumHeroesUseCase;
use App\Heroes\Application\UseCase\UpdateHeroUseCase;
use App\Notifications\Application\ClearNotificationsUseCase;
use App\Notifications\Application\ListNotificationsUseCase;
use App\Activities\Application\UseCase\ClearActivityLogUseCase;
use App\Activities\Application\UseCase\ListActivityLogUseCase;
use App\Activities\Application\UseCase\RecordActivityUseCase;
use App\Application\Comics\GenerateComicUseCase;
use App\AI\ComicGeneratorFactory;
use App\Shared\Infrastructure\Filesystem\LocalFilesystem;

final class ServiceContainer
{
    /**
     * @param array<string, mixed> $persistence
     * @param array<string, mixed> $events
     * @param array<string, mixed> $services
     * @return array<string, mixed>
     */
    public static function build(
        array $persistence,
        array $events,
        array $services,
        string $rootPath,
        ?string $openAiServiceUrl
    ): array {
        $albumRepository = $persistence['albumRepository'];
        $heroRepository = $persistence['heroRepository'];
        $eventBus = $events['eventBus'];
        $notificationRepository = $events['notificationRepository'];
        $activityRepository = $services['activityRepository'];
        $ragSyncer = $services['ragSyncer'];

        $findAlbum = new FindAlbumUseCase($albumRepository);
        $updateAlbum = new UpdateAlbumUseCase($albumRepository, $eventBus);
        
        $createHero = new CreateHeroUseCase($heroRepository, $albumRepository, $eventBus, $ragSyncer);

        return [
            'createAlbum'    => new CreateAlbumUseCase($albumRepository),
            'seedAlbumHeroes' => new SeedAlbumHeroesUseCase($albumRepository, $heroRepository, $eventBus),
            'updateAlbum'    => $updateAlbum,
            'listAlbums'     => new ListAlbumsUseCase($albumRepository),
            'deleteAlbum'    => new DeleteAlbumUseCase($albumRepository, $heroRepository),
            'findAlbum'      => $findAlbum,
            'createHero'     => $createHero,
            'listHeroes'     => new ListHeroesUseCase($heroRepository),
            'findHero'       => new FindHeroUseCase($heroRepository),
            'deleteHero'     => new DeleteHeroUseCase($heroRepository),
            'updateHero'     => new UpdateHeroUseCase($heroRepository, $ragSyncer),
            'clearNotifications' => new ClearNotificationsUseCase($notificationRepository),
            'listNotifications'  => new ListNotificationsUseCase($notificationRepository),
            'recordActivity'     => new RecordActivityUseCase($activityRepository),
            'listActivity'       => new ListActivityLogUseCase($activityRepository),
            'clearActivity'      => new ClearActivityLogUseCase($activityRepository),
            'generateComic'      => new GenerateComicUseCase(
                ComicGeneratorFactory::create(
                    $_ENV['LLM_PROVIDER'] ?? getenv('LLM_PROVIDER') ?: 'openai',
                    $openAiServiceUrl
                ),
                new FindHeroUseCase($heroRepository)
            ),
            'uploadAlbumCover'   => new UploadAlbumCoverUseCase(
                new LocalFilesystem(
                    defined('ALBUM_UPLOAD_DIR') ? ALBUM_UPLOAD_DIR : ($rootPath . '/public/uploads/albums'),
                    defined('ALBUM_UPLOAD_URL_PREFIX') ? ALBUM_UPLOAD_URL_PREFIX : '/uploads/albums/'
                ),
                $findAlbum,
                $updateAlbum
            ),
        ];
    }
}
