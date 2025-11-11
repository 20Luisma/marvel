<?php

declare(strict_types=1);

namespace App\Heroes\Application\UseCase;

use App\Heroes\Application\DTO\HeroResponse;
use App\Heroes\Domain\Entity\Hero;
use App\Heroes\Domain\Repository\HeroRepository;

final class ListHeroesUseCase
{
    public function __construct(private readonly HeroRepository $repository)
    {
    }

    /**
     * @return array<int, array{heroId: string, albumId: string, nombre: string, slug: string, contenido: string, imagen: string, createdAt: string, updatedAt: string}>
     */
    public function execute(?string $albumId = null): array
    {
        $heroes = ($albumId !== null && $albumId !== '')
            ? $this->repository->byAlbum($albumId)
            : $this->repository->all();

        return array_map(
            static fn (Hero $hero): array => HeroResponse::fromHero($hero)->toArray(),
            $heroes
        );
    }
}
