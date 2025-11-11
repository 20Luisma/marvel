<?php

declare(strict_types=1);

namespace App\Albums\Application\UseCase;

use App\Albums\Domain\Repository\AlbumRepository;
use App\Heroes\Domain\Repository\HeroRepository;
use InvalidArgumentException;

final class DeleteAlbumUseCase
{
    public function __construct(
        private readonly AlbumRepository $albumRepository,
        private readonly HeroRepository $heroRepository
    ) {
    }

    public function execute(string $albumId): void
    {
        $album = $this->albumRepository->find($albumId);

        if ($album === null) {
            throw new InvalidArgumentException('Álbum no encontrado.');
        }

        $this->heroRepository->deleteByAlbum($albumId);
        $this->albumRepository->delete($albumId);
    }
}
