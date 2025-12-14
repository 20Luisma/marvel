<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Heroes\Domain\Entity\Hero;
use App\Heroes\Domain\Repository\HeroRepository;

final class SpyHeroRepository implements HeroRepository
{
    public int $saveCalls = 0;

    public function save(Hero $hero): void
    {
        $this->saveCalls++;
    }

    public function byAlbum(string $albumId): array
    {
        return [];
    }

    public function all(): array
    {
        return [];
    }

    public function find(string $heroId): ?Hero
    {
        return null;
    }

    public function delete(string $heroId): void
    {
    }

    public function deleteByAlbum(string $albumId): void
    {
    }
}

