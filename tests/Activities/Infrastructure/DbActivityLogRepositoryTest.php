<?php

declare(strict_types=1);

namespace Tests\Activities\Infrastructure;

use App\Activities\Domain\ActivityEntry;
use App\Activities\Domain\ActivityScope;
use App\Activities\Infrastructure\Persistence\DbActivityLogRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class DbActivityLogRepositoryTest extends TestCase
{
    private PDO $pdo;
    private DbActivityLogRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec(
            'CREATE TABLE activity_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                scope TEXT NOT NULL,
                context_id TEXT NULL,
                action TEXT NOT NULL,
                title TEXT NOT NULL,
                occurred_at TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
        $this->repository = new DbActivityLogRepository($this->pdo, 50);
    }

    // Agrega y lista actividades por scope respetando la normalización de contextos.
    public function testAppendAndListByScopeAndContext(): void
    {
        $first = ActivityEntry::create(ActivityScope::ALBUMS, null, 'created', 'Album 1');
        $second = ActivityEntry::create(ActivityScope::ALBUMS, 'album-1', 'updated', 'Album actualizado'); // context se normaliza a null en albums
        $third = ActivityEntry::create(ActivityScope::HEROES, 'hero-1', 'created', 'Hero creado');

        $this->repository->append($first);
        $this->repository->append($second);
        $this->repository->append($third);

        $allAlbums = $this->repository->all(ActivityScope::ALBUMS);
        $heroContext = $this->repository->all(ActivityScope::HEROES, 'hero-1');

        self::assertCount(2, $allAlbums);
        self::assertSame('updated', $allAlbums[0]->action()); // orden descendente

        self::assertCount(1, $heroContext);
        self::assertSame('created', $heroContext[0]->action());

        $albumContext = $this->repository->all(ActivityScope::ALBUMS, 'album-1');
        self::assertCount(0, $albumContext); // filtros de contexto solo aplican a scopes que lo requieren
    }

    // Borra actividades por scope y contexto específicos.
    public function testClearRemovesEntriesByScopeAndContext(): void
    {
        $this->repository->append(ActivityEntry::create(ActivityScope::HEROES, 'hero-1', 'created', 'Hero A'));
        $this->repository->append(ActivityEntry::create(ActivityScope::HEROES, 'hero-2', 'created', 'Hero B'));

        $this->repository->clear(ActivityScope::HEROES, 'hero-1');

        self::assertCount(0, $this->repository->all(ActivityScope::HEROES, 'hero-1'));
        self::assertCount(1, $this->repository->all(ActivityScope::HEROES, 'hero-2'));
    }
}
