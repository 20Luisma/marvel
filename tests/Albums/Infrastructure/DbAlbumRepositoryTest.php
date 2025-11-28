<?php

declare(strict_types=1);

namespace Tests\Albums\Infrastructure;

use App\Albums\Domain\Entity\Album;
use App\Albums\Infrastructure\Persistence\DbAlbumRepository;
use PHPUnit\Framework\TestCase;
use Tests\Support\SqliteMySqlUpsertPdo;

final class DbAlbumRepositoryTest extends TestCase
{
    private SqliteMySqlUpsertPdo $pdo;
    private DbAlbumRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = new SqliteMySqlUpsertPdo();
        $this->pdo->exec('CREATE TABLE albums (album_id TEXT PRIMARY KEY, nombre TEXT NOT NULL, cover_image TEXT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');
        $this->repository = new DbAlbumRepository($this->pdo);
    }

    // Guarda y recupera un álbum usando upsert compatible con SQLite.
    public function testSaveAndFindAlbum(): void
    {
        $album = Album::create('album-1', 'Guardians', null);
        $this->repository->save($album);

        $stored = $this->repository->find('album-1');

        self::assertNotNull($stored);
        self::assertSame('Guardians', $stored->nombre());
        self::assertNull($stored->coverImage());
    }

    // Actualiza un álbum existente manteniendo la misma clave primaria.
    public function testUpsertUpdatesExistingAlbum(): void
    {
        $album = Album::create('album-1', 'Guardians', null);
        $this->repository->save($album);

        $album->renombrar('Guardians 2');
        $album->actualizarCover('cover.png');
        $this->repository->save($album);

        $stored = $this->repository->find('album-1');

        self::assertNotNull($stored);
        self::assertSame('Guardians 2', $stored->nombre());
        self::assertSame('cover.png', $stored->coverImage());
    }

    // Devuelve null cuando se busca un álbum inexistente.
    public function testFindReturnsNullForMissingAlbum(): void
    {
        self::assertNull($this->repository->find('missing'));
    }

    // Elimina un álbum existente.
    public function testDeleteRemovesAlbum(): void
    {
        $album = Album::create('album-1', 'Guardians', null);
        $this->repository->save($album);

        $this->repository->delete('album-1');

        self::assertNull($this->repository->find('album-1'));
        self::assertSame([], $this->repository->all());
    }
}
