<?php

declare(strict_types=1);

namespace App\Albums\Application\UseCase;

use App\Albums\Application\DTO\AlbumResponse;
use App\Albums\Application\DTO\CreateAlbumRequest;
use App\Albums\Domain\Entity\Album;
use App\Albums\Domain\Repository\AlbumRepository;

final class CreateAlbumUseCase
{
    public function __construct(private readonly AlbumRepository $repository)
    {
    }

    public function execute(CreateAlbumRequest $request): AlbumResponse
    {
        $album = Album::create(
            self::generateUuid(),
            $request->nombre(),
            $request->coverImage()
        );
        $this->repository->save($album);

        return AlbumResponse::fromAlbum($album);
    }

    private static function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        $segments = [
            bin2hex(substr($data, 0, 4)),
            bin2hex(substr($data, 4, 2)),
            bin2hex(substr($data, 6, 2)),
            bin2hex(substr($data, 8, 2)),
            bin2hex(substr($data, 10, 6)),
        ];

        return implode('-', $segments);
    }
}
