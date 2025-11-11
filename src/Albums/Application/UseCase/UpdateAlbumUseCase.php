<?php

declare(strict_types=1);

namespace App\Albums\Application\UseCase;

use App\Albums\Application\DTO\AlbumResponse;
use App\Albums\Application\DTO\UpdateAlbumRequest;
use App\Albums\Domain\Event\AlbumUpdated;
use App\Albums\Domain\Repository\AlbumRepository;
use App\Shared\Domain\Bus\EventBus;
use InvalidArgumentException;

final class UpdateAlbumUseCase
{
    public function __construct(
        private readonly AlbumRepository $repository,
        private readonly EventBus $eventBus
    ) {
    }

    public function execute(UpdateAlbumRequest $request): AlbumResponse
    {
        $album = $this->repository->find($request->albumId());

        if ($album === null) {
            throw new InvalidArgumentException('Álbum no encontrado.');
        }

        $hasChanges = false;

        $nuevoNombre = $request->nombre();
        if ($nuevoNombre !== null && $nuevoNombre !== $album->nombre()) {
            $album->renombrar($nuevoNombre);
            $hasChanges = true;
        }

        if ($request->hasCoverImage()) {
            $nuevoCover = $request->coverImage();
            if ($nuevoCover !== $album->coverImage()) {
                $album->actualizarCover($nuevoCover);
                $hasChanges = true;
            }
        }

        if ($hasChanges) {
            $this->repository->save($album);
            $this->eventBus->publish([AlbumUpdated::fromAlbum($album)]);
        }

        return AlbumResponse::fromAlbum($album);
    }
}
