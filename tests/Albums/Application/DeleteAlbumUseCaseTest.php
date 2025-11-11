<?php

declare(strict_types=1);

namespace Tests\Albums\Application;

use App\Albums\Application\UseCase\DeleteAlbumUseCase;
use App\Albums\Domain\Entity\Album;
use App\Heroes\Domain\Entity\Hero;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemoryAlbumRepository;
use Tests\Doubles\InMemoryHeroRepository;

final class DeleteAlbumUseCaseTest extends TestCase
{
    public function testItDeletesAlbumAndHeroes(): void
    {
        $albumRepository = new InMemoryAlbumRepository();
        $heroRepository = new InMemoryHeroRepository();

        $album = Album::create('album-1', 'Test Album');
        $albumRepository->seed($album);

        $hero1 = Hero::create('hero-1', 'album-1', 'Hero 1', 'Contenido 1', 'img.jpg');
        $hero2 = Hero::create('hero-2', 'album-1', 'Hero 2', 'Contenido 2', 'img.jpg');
        $heroRepository->seed($hero1, $hero2);

        $useCase = new DeleteAlbumUseCase($albumRepository, $heroRepository);
        $useCase->execute('album-1');

        self::assertNull($albumRepository->find('album-1'));
        self::assertEmpty($heroRepository->byAlbum('album-1'));
    }

    public function testItFailsWhenAlbumDoesNotExist(): void
    {
        $albumRepository = new InMemoryAlbumRepository();
        $heroRepository = new InMemoryHeroRepository();

        $this->expectException(InvalidArgumentException::class);

        $useCase = new DeleteAlbumUseCase($albumRepository, $heroRepository);
        $useCase->execute('non-existent-album');
    }
}
