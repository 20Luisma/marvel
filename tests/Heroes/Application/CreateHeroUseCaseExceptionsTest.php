<?php

declare(strict_types=1);

namespace Tests\Heroes\Application;

use App\Albums\Domain\Repository\AlbumRepository;
use App\Heroes\Application\DTO\CreateHeroRequest;
use App\Heroes\Application\UseCase\CreateHeroUseCase;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\SpyEventBus;
use Tests\Doubles\SpyHeroRepository;
use Tests\Doubles\SpyRagSyncer;

final class CreateHeroUseCaseExceptionsTest extends TestCase
{
    public function testItDoesNotPersistPublishOrSyncWhenAlbumDoesNotExist(): void
    {
        $albumRepository = new class implements AlbumRepository {
            public function save(\App\Albums\Domain\Entity\Album $album): void
            {
            }

            public function all(): array
            {
                return [];
            }

            public function find(string $albumId): ?\App\Albums\Domain\Entity\Album
            {
                return null;
            }

            public function delete(string $albumId): void
            {
            }
        };

        $heroRepository = new SpyHeroRepository();
        $eventBus = new SpyEventBus();
        $ragSyncer = new SpyRagSyncer();

        $useCase = new CreateHeroUseCase($heroRepository, $albumRepository, $eventBus, $ragSyncer);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El álbum indicado no existe.');

        try {
            $useCase->execute(new CreateHeroRequest('missing', 'Thor', 'God of Thunder', 'https://example.com/thor.jpg'));
        } finally {
            self::assertSame(0, $heroRepository->saveCalls);
            self::assertSame(0, $eventBus->publishCalls);
            self::assertSame(0, $ragSyncer->syncCalls);
        }
    }

    public function testItDoesNotPersistPublishOrSyncWhenDomainRejectsHeroData(): void
    {
        $album = \App\Albums\Domain\Entity\Album::create('album-1', 'Marvel');
        $albumRepository = new class($album) implements AlbumRepository {
            public function __construct(private readonly \App\Albums\Domain\Entity\Album $album)
            {
            }

            public function save(\App\Albums\Domain\Entity\Album $album): void
            {
            }

            public function all(): array
            {
                return [$this->album];
            }

            public function find(string $albumId): ?\App\Albums\Domain\Entity\Album
            {
                return $albumId === $this->album->albumId() ? $this->album : null;
            }

            public function delete(string $albumId): void
            {
            }
        };

        $heroRepository = new SpyHeroRepository();
        $eventBus = new SpyEventBus();
        $ragSyncer = new SpyRagSyncer();

        $useCase = new CreateHeroUseCase($heroRepository, $albumRepository, $eventBus, $ragSyncer);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El nombre del héroe no puede estar vacío');

        try {
            $useCase->execute(new CreateHeroRequest('album-1', '   ', 'Contenido', 'https://example.com/img.jpg'));
        } finally {
            self::assertSame(0, $heroRepository->saveCalls);
            self::assertSame(0, $eventBus->publishCalls);
            self::assertSame(0, $ragSyncer->syncCalls);
        }
    }
}
