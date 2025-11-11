<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Albums\Domain\Entity\Album;
use App\Albums\Domain\Repository\AlbumRepository;

final class InMemoryAlbumRepository implements AlbumRepository
{
    /**
     * @var array<string, Album>
     */
    private array $albums = [];

    public function save(Album $album): void
    {
        $this->albums[$album->albumId()] = $album;
    }

    /**
     * @return array<int, Album>
     */
    public function all(): array
    {
        $albums = array_values($this->albums);

        usort(
            $albums,
            static fn (Album $a, Album $b): int => $a->createdAt() <=> $b->createdAt()
        );

        return $albums;
    }

    public function find(string $albumId): ?Album
    {
        return $this->albums[$albumId] ?? null;
    }

    public function delete(string $albumId): void
    {
        unset($this->albums[$albumId]);
    }

    public function seed(Album ...$albums): void
    {
        foreach ($albums as $album) {
            $this->save($album);
        }
    }
}
