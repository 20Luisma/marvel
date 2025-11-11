<?php

declare(strict_types=1);

namespace App\Albums\Application\DTO;

use App\Albums\Domain\Entity\Album;

final class AlbumResponse
{
    public function __construct(
        private readonly string $albumId,
        private readonly string $nombre,
        private readonly ?string $coverImage,
        private readonly string $createdAt,
        private readonly string $updatedAt
    ) {
    }

    public static function fromAlbum(Album $album): self
    {
        $primitives = $album->toPrimitives();
        return new self(
            $primitives['albumId'],
            $primitives['nombre'],
            $primitives['coverImage'] ?? null,
            $primitives['createdAt'],
            $primitives['updatedAt'] ?? $primitives['createdAt']
        );
    }

    /**
     * @return array{albumId: string, nombre: string, coverImage: ?string, createdAt: string, updatedAt: string}
     */
    public function toArray(): array
    {
        return [
            'albumId' => $this->albumId,
            'nombre' => $this->nombre,
            'coverImage' => $this->coverImage,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
