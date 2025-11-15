<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Heroes\Domain\Entity\Hero;
use App\Heroes\Domain\Repository\HeroRepository;
use App\Shared\Util\Slugger;
use DateTimeImmutable;
use InvalidArgumentException;

final class FakeHeroRepository implements HeroRepository
{
    /**
     * @var array<string, Hero>
     */
    private array $heroes = [];

    private int $autoIncrement = 1;

    public function save(array|Hero $hero): void
    {
        if ($hero instanceof Hero) {
            $this->heroes[$hero->heroId()] = $hero;

            return;
        }

        if (is_array($hero)) {
            $entity = $this->hydrateHeroFromArray($hero);
            $this->heroes[$entity->heroId()] = $entity;

            return;
        }

        throw new InvalidArgumentException('FakeHeroRepository::save expects a Hero entity or an array payload.');
    }

    /**
     * @return array<int, Hero>
     */
    public function byAlbum(string $albumId): array
    {
        return array_values(array_filter(
            $this->heroes,
            static fn (Hero $hero): bool => $hero->albumId() === $albumId
        ));
    }

    /**
     * @return array<int, Hero>
     */
    public function all(): array
    {
        return array_values($this->heroes);
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
        foreach ($this->heroes as $heroId => $hero) {
            if ($hero->albumId() === $albumId) {
                unset($this->heroes[$heroId]);
            }
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function findAll(): array
    {
        return array_map(
            static fn (Hero $hero): array => $hero->toPrimitives(),
            array_values($this->heroes)
        );
    }

    public function clear(): void
    {
        $this->heroes = [];
        $this->autoIncrement = 1;
    }

    private function hydrateHeroFromArray(array $data): Hero
    {
        $nombre = (string) ($data['nombre'] ?? $data['name'] ?? '');
        $contenido = (string) ($data['contenido'] ?? $data['content'] ?? '');
        $imagen = (string) ($data['imagen'] ?? $data['image'] ?? '');
        $albumId = (string) ($data['albumId'] ?? '');

        $heroId = (string) ($data['heroId'] ?? $this->generateId());
        $slug = (string) ($data['slug'] ?? Slugger::slugify($nombre));
        $createdAt = (string) ($data['createdAt'] ?? (new DateTimeImmutable())->format(DATE_ATOM));
        $updatedAt = (string) ($data['updatedAt'] ?? $createdAt);

        return Hero::fromPrimitives([
            'heroId' => $heroId,
            'albumId' => $albumId,
            'nombre' => $nombre,
            'slug' => $slug,
            'contenido' => $contenido,
            'imagen' => $imagen,
            'createdAt' => $createdAt,
            'updatedAt' => $updatedAt,
        ]);
    }

    private function generateId(): string
    {
        return sprintf('fake-hero-%d', $this->autoIncrement++);
    }
}
