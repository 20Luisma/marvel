<?php

declare(strict_types=1);

namespace App\Albums\Application\UseCase;

use App\Albums\Application\DTO\AlbumResponse;
use App\Albums\Domain\Repository\AlbumRepository;
use InvalidArgumentException;

final class FindAlbumUseCase
{
    public function __construct(private readonly AlbumRepository $repository)
    {
    }

    /**
     * @return array<string,mixed>
     */
    public function execute(string $albumId): array
    {
        $album = $this->repository->find($albumId);

        if ($album === null) {
            throw new InvalidArgumentException('Álbum no encontrado.');
        }

        return AlbumResponse::fromAlbum($album)->toArray();
    }
}
