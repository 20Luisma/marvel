<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Heroes\Domain\Entity\Hero;
use App\Heroes\Domain\Repository\HeroRepository;

final class InMemoryHeroRepository implements HeroRepository
{
    /**
     * @var array<string, Hero>
     */
    private array $heroes = [];

    public function save(Hero $hero): void
    {
        $this->heroes[$hero->heroId()] = $hero;
    }

    /**
     * @return array<int, Hero>
     */
    public function byAlbum(string $albumId): array
    {
        $filtered = array_values(array_filter(
            $this->heroes,
            static fn (Hero $hero): bool => $hero->albumId() === $albumId
        ));

        usort(
            $filtered,
            static fn (Hero $a, Hero $b): int => $a->createdAt() <=> $b->createdAt()
        );

        return $filtered;
    }

    /**
     * @return array<int, Hero>
     */
    public function all(): array
    {
        $all = array_values($this->heroes);

        usort(
            $all,
            static fn (Hero $a, Hero $b): int => $a->createdAt() <=> $b->createdAt()
        );

        return $all;
    }

    public function find(string $heroId): ?Hero
    {
        return $this->heroes[$heroId] ?? null;
    }

    public function delete(string $heroId): void
    {
        unset($this->heroes[$heroId]);
    }

    public function deleteByAlbum(string $albumId): void
    {
        foreach ($this->heroes as $id => $hero) {
            if ($hero->albumId() === $albumId) {
                unset($this->heroes[$id]);
            }
        }
    }

    public function seed(Hero ...$heroes): void
    {
        foreach ($heroes as $hero) {
            $this->save($hero);
        }
    }
}
