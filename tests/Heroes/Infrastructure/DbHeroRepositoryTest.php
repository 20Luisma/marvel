<?php

declare(strict_types=1);

namespace Tests\Heroes\Infrastructure;

use App\Albums\Domain\Entity\Album;
use App\Albums\Infrastructure\Persistence\DbAlbumRepository;
use App\Heroes\Domain\Entity\Hero;
use App\Heroes\Infrastructure\Persistence\DbHeroRepository;
use PDOException;
use PHPUnit\Framework\TestCase;
use Tests\Support\SqliteMySqlUpsertPdo;

final class DbHeroRepositoryTest extends TestCase
{
    private SqliteMySqlUpsertPdo $pdo;
    private DbAlbumRepository $albumRepository;
    private DbHeroRepository $heroRepository;

    protected function setUp(): void
    {
        $this->pdo = new SqliteMySqlUpsertPdo();
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec('CREATE TABLE albums (album_id TEXT PRIMARY KEY, nombre TEXT NOT NULL, cover_image TEXT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');
        $this->pdo->exec('CREATE TABLE heroes (hero_id TEXT PRIMARY KEY, album_id TEXT NOT NULL, nombre TEXT NOT NULL, slug TEXT NOT NULL, contenido TEXT NOT NULL, imagen TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL, FOREIGN KEY (album_id) REFERENCES albums(album_id) ON DELETE CASCADE)');

        $this->albumRepository = new DbAlbumRepository($this->pdo);
        $this->heroRepository = new DbHeroRepository($this->pdo);

        $this->albumRepository->save(Album::create('album-1', 'Guardians'));
    }

    // Guarda y recupera héroes por álbum.
    public function testSaveAndRetrieveHeroesByAlbum(): void
    {
        $hero = Hero::create('hero-1', 'album-1', 'Star-Lord', 'Leader', 'image.png');
        $this->heroRepository->save($hero);

        $found = $this->heroRepository->find('hero-1');
        $byAlbum = $this->heroRepository->byAlbum('album-1');

        self::assertNotNull($found);
        self::assertCount(1, $byAlbum);
        self::assertSame('Star-Lord', $byAlbum[0]->nombre());
    }

    // Actualiza un héroe existente y persiste el slug recalculado.
    public function testUpsertUpdatesExistingHero(): void
    {
        $hero = Hero::create('hero-1', 'album-1', 'Star-Lord', 'Leader', 'image.png');
        $this->heroRepository->save($hero);

        $hero->rename('Peter Quill');
        $hero->updateContent('Updated bio');
        $this->heroRepository->save($hero);

        $updated = $this->heroRepository->find('hero-1');

        self::assertNotNull($updated);
        self::assertSame('peter-quill', $updated->slug());
        self::assertSame('Updated bio', $updated->contenido());
    }

    // Devuelve null cuando se busca un héroe inexistente.
    public function testFindReturnsNullForMissingHero(): void
    {
        self::assertNull($this->heroRepository->find('missing-hero'));
    }

    // Borra héroes asociados a un álbum y los excluye de las consultas.
    public function testDeleteByAlbumRemovesHeroes(): void
    {
        $hero = Hero::create('hero-1', 'album-1', 'Star-Lord', 'Leader', 'image.png');
        $this->heroRepository->save($hero);
        $this->heroRepository->deleteByAlbum('album-1');

        self::assertCount(0, $this->heroRepository->byAlbum('album-1'));
        self::assertNull($this->heroRepository->find('hero-1'));
    }

    // Falla al guardar un héroe vinculado a un álbum inexistente.
    public function testSaveFailsWhenAlbumDoesNotExist(): void
    {
        $hero = Hero::create('hero-2', 'album-missing', 'Gamora', 'Warrior', 'image.png');

        $this->expectException(PDOException::class);

        $this->heroRepository->save($hero);
    }
}
