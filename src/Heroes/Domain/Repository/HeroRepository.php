<?php

declare(strict_types=1);

namespace App\Heroes\Domain\Repository;

use App\Heroes\Domain\Entity\Hero;

interface HeroRepository
{
    public function save(Hero $hero): void;

    /**
     * @return array<int, Hero>
     */
    public function byAlbum(string $albumId): array;

    /**
     * @return array<int, Hero>
     */
    public function all(): array;

    public function find(string $heroId): ?Hero;

    public function delete(string $heroId): void;

    public function deleteByAlbum(string $albumId): void;
}
