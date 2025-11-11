<?php

declare(strict_types=1);

namespace App\Heroes\Application\DTO;

final class CreateHeroRequest
{
    public function __construct(
        private readonly string $albumId,
        private readonly string $nombre,
        private readonly string $contenido,
        private readonly string $imagen
    ) {
    }

    public function albumId(): string
    {
        return $this->albumId;
    }

    public function nombre(): string
    {
        return $this->nombre;
    }

    public function contenido(): string
    {
        return $this->contenido;
    }

    public function imagen(): string
    {
        return $this->imagen;
    }
}
