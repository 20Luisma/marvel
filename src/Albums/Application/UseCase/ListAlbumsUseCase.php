<?php

declare(strict_types=1);

namespace App\Albums\Application\UseCase;

use App\Albums\Application\DTO\AlbumResponse;
use App\Albums\Domain\Entity\Album;
use App\Albums\Domain\Repository\AlbumRepository;

final class ListAlbumsUseCase
{
    public function __construct(private readonly AlbumRepository $repository)
    {
    }

    /**
     * @return array<int, array{albumId: string, nombre: string, coverImage: ?string, createdAt: string, updatedAt: string}>
     */
    public function execute(): array
    {
        return array_map(
            static fn (Album $album): array => AlbumResponse::fromAlbum($album)->toArray(),
            $this->repository->all()
        );
    }
}
