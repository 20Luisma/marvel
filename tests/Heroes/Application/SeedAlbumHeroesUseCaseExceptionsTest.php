<?php

declare(strict_types=1);

namespace Tests\Heroes\Application;

use App\Albums\Domain\Repository\AlbumRepository;
use App\Heroes\Application\UseCase\SeedAlbumHeroesUseCase;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\SpyEventBus;
use Tests\Doubles\SpyHeroRepository;

final class SeedAlbumHeroesUseCaseExceptionsTest extends TestCase
{
    public function testItDoesNotPersistOrPublishWhenAlbumDoesNotExist(): void
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

        $useCase = new SeedAlbumHeroesUseCase($albumRepository, $heroRepository, $eventBus);

        try {
            $useCase->execute('missing');
            self::fail('Expected exception was not thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Álbum no encontrado.', $exception->getMessage());
        }

        self::assertSame(0, $heroRepository->saveCalls);
        self::assertSame(0, $eventBus->publishCalls);
    }
}
