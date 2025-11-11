<?php

declare(strict_types=1);

namespace App\Albums\Domain\Repository;

use App\Albums\Domain\Entity\Album;

interface AlbumRepository
{
    public function save(Album $album): void;

    /**
     * @return array<int, Album>
     */
    public function all(): array;

    public function find(string $albumId): ?Album;

    public function delete(string $albumId): void;
}
