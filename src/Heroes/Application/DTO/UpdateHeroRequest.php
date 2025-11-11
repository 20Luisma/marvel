<?php

declare(strict_types=1);

namespace App\Heroes\Application\DTO;

final class UpdateHeroRequest
{
    public function __construct(
        private readonly string $heroId,
        private readonly ?string $nombre,
        private readonly ?string $contenido,
        private readonly ?string $imagen
    ) {
    }

    public function heroId(): string
    {
        return $this->heroId;
    }

    public function nombre(): ?string
    {
        return $this->nombre;
    }

    public function contenido(): ?string
    {
        return $this->contenido;
    }

    public function imagen(): ?string
    {
        return $this->imagen;
    }
}
