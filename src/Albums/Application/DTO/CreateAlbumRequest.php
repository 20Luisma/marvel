<?php

declare(strict_types=1);

namespace App\Albums\Application\DTO;

final class CreateAlbumRequest
{
    public function __construct(
        private readonly string $nombre,
        private readonly ?string $coverImage = null
    ) {
    }

    public function nombre(): string
    {
        return $this->nombre;
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
