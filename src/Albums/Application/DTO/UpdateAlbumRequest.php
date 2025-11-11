<?php

declare(strict_types=1);

namespace App\Albums\Application\DTO;

final class UpdateAlbumRequest
{
    public function __construct(
        private readonly string $albumId,
        private readonly ?string $nombre,
        private readonly ?string $coverImage,
        private readonly bool $coverProvided
    ) {
    }

    public function albumId(): string
    {
        return $this->albumId;
    }

    public function nombre(): ?string
    {
        if ($this->nombre === null) {
            return null;
        }

        $trimmed = trim($this->nombre);

        return $trimmed === '' ? null : $trimmed;
    }

    public function hasCoverImage(): bool
    {
        return $this->coverProvided;
    }

    public function coverImage(): ?string
    {
        if ($this->coverImage === null) {
            return null;
        }

        $trimmed = trim($this->coverImage);

        return $trimmed === '' ? null : $trimmed;
    }
}
