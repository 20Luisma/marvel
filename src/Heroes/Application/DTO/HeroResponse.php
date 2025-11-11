<?php

declare(strict_types=1);

namespace App\Heroes\Application\DTO;

use App\Heroes\Domain\Entity\Hero;

final class HeroResponse
{
    public function __construct(
        private readonly string $heroId,
        private readonly string $albumId,
        private readonly string $nombre,
        private readonly string $slug,
        private readonly string $contenido,
        private readonly string $imagen,
        private readonly string $createdAt,
        private readonly string $updatedAt
    ) {
    }

    public static function fromHero(Hero $hero): self
    {
        return new self(
            $hero->heroId(),
            $hero->albumId(),
            $hero->nombre(),
            $hero->slug(),
            $hero->contenido(),
            $hero->imagen(),
            $hero->createdAt()->format(DATE_ATOM),
            $hero->updatedAt()->format(DATE_ATOM)
        );
    }

    /**
     * @return array{heroId: string, albumId: string, nombre: string, slug: string, contenido: string, imagen: string, createdAt: string, updatedAt: string}
     */
    public function toArray(): array
    {
        return [
            'heroId' => $this->heroId,
            'albumId' => $this->albumId,
            'nombre' => $this->nombre,
            'slug' => $this->slug,
            'contenido' => $this->contenido,
            'imagen' => $this->imagen,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
