<?php

declare(strict_types=1);

namespace Tests\Application;

use App\Albums\Application\DTO\CreateAlbumRequest;
use App\Albums\Application\UseCase\CreateAlbumUseCase;
use App\Albums\Infrastructure\Persistence\DbAlbumRepository;
use App\Heroes\Application\DTO\CreateHeroRequest;
use App\Heroes\Application\UseCase\CreateHeroUseCase;
use App\Heroes\Infrastructure\Persistence\DbHeroRepository;
use App\Notifications\Application\HeroCreatedNotificationHandler;
use App\Notifications\Infrastructure\FileNotificationSender;
use App\Shared\Infrastructure\Bus\InMemoryEventBus;
use PHPUnit\Framework\TestCase;
use Tests\Support\SqliteMySqlUpsertPdo;

final class CreateHeroWithPersistenceAndNotificationTest extends TestCase
{
    private ?string $notificationFile = null;

    protected function tearDown(): void
    {
        if ($this->notificationFile !== null && is_file($this->notificationFile)) {
            unlink($this->notificationFile);
        }
    }

    public function testItCreatesHeroPersistsAndEmitsNotification(): void
    {
        $pdo = new SqliteMySqlUpsertPdo();
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('CREATE TABLE albums (album_id TEXT PRIMARY KEY, nombre TEXT NOT NULL, cover_image TEXT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE heroes (hero_id TEXT PRIMARY KEY, album_id TEXT NOT NULL, nombre TEXT NOT NULL, slug TEXT NOT NULL, contenido TEXT NOT NULL, imagen TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL, FOREIGN KEY (album_id) REFERENCES albums(album_id) ON DELETE CASCADE)');

        $albumRepository = new DbAlbumRepository($pdo);
        $heroRepository = new DbHeroRepository($pdo);

        $eventBus = new InMemoryEventBus();
        $this->notificationFile = sys_get_temp_dir() . '/hero-notifications-' . bin2hex(random_bytes(6)) . '.log';
        $eventBus->subscribe(new HeroCreatedNotificationHandler(new FileNotificationSender($this->notificationFile)));

        $createAlbum = new CreateAlbumUseCase($albumRepository);
        $album = $createAlbum->execute(new CreateAlbumRequest('Álbum Integración'));
        $albumId = $album->toArray()['albumId'];

        $createHero = new CreateHeroUseCase($heroRepository, $albumRepository, $eventBus);
        $hero = $createHero->execute(new CreateHeroRequest(
            $albumId,
            'Black Panther',
            'Rey de Wakanda.',
            'https://example.com/panther.jpg'
        ));

        $stored = $heroRepository->find($hero->toArray()['heroId']);
        self::assertNotNull($stored);
        self::assertSame('Black Panther', $stored->nombre());

        $notificationLog = file_get_contents($this->notificationFile);
        self::assertNotFalse($notificationLog);
        self::assertStringContainsString('Nuevo héroe creado: Black Panther (álbum: Álbum Integración)', $notificationLog);
    }
}
