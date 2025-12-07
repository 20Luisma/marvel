<?php

declare(strict_types=1);

namespace App\Heroes\Infrastructure\Persistence;

use App\Heroes\Domain\Entity\Hero;
use App\Heroes\Domain\Repository\HeroRepository;
use App\Shared\Util\Slugger;

final class FileHeroRepository implements HeroRepository
{
    public function __construct(private readonly string $storagePath)
    {
        $this->ensureStorage();
    }

    public function save(Hero $hero): void
    {
        $records = $this->loadRecords();
        $records = array_values(array_filter(
            $records,
            static fn (array $data): bool => $data['heroId'] !== $hero->heroId()
        ));

        $records[] = $hero->toPrimitives();

        usort(
            $records,
            static fn (array $a, array $b): int => strcmp($a['createdAt'], $b['createdAt'])
        );

        $this->persistRecords($records);
    }

    /**
     * @return array<int, Hero>
     */
    public function byAlbum(string $albumId): array
    {
        $records = array_values(array_filter(
            $this->loadRecords(),
            static fn (array $data): bool => $data['albumId'] === $albumId
        ));

        return array_map(
            static fn (array $data): Hero => Hero::fromPrimitives($data),
            $records
        );
    }

    /**
     * @return array<int, Hero>
     */
    public function all(): array
    {
        return array_map(
            static fn (array $data): Hero => Hero::fromPrimitives($data),
            $this->loadRecords()
        );
    }

    public function find(string $heroId): ?Hero
    {
        foreach ($this->loadRecords() as $data) {
            if ($data['heroId'] === $heroId) {
                return Hero::fromPrimitives($data);
            }
        }

        return null;
    }

    public function delete(string $heroId): void
    {
        $records = $this->loadRecords();
        $filtered = array_values(array_filter(
            $records,
            static fn (array $data): bool => $data['heroId'] !== $heroId
        ));

        if (count($filtered) !== count($records)) {
            $this->persistRecords($filtered);
        }
    }

    public function deleteByAlbum(string $albumId): void
    {
        $records = $this->loadRecords();
        $filtered = array_values(array_filter(
            $records,
            static fn (array $data): bool => $data['albumId'] !== $albumId
        ));

        if (count($filtered) !== count($records)) {
            $this->persistRecords($filtered);
        }
    }

    /**
     * @return array<int, array{heroId: string, albumId: string, nombre: string, slug: string, contenido: string, imagen: string, createdAt: string, updatedAt: string}>
     */
    private function loadRecords(): array
    {
        if (!is_file($this->storagePath)) {
            return [];
        }

        $contents = file_get_contents($this->storagePath);

        if ($contents === false || trim($contents) === '') {
            return [];
        }

        $decoded = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return [];
        }

        if (!array_is_list($decoded)) {
            $decoded = array_values(array_filter($decoded, 'is_array'));
        }

        $decoded = array_values(array_filter(
            $decoded,
            static fn ($item): bool => is_array($item)
        ));

        usort(
            $decoded,
            static fn (array $a, array $b): int => strcmp($a['createdAt'] ?? '', $b['createdAt'] ?? '')
        );

        return array_map(
            static fn (array $data): array => [
                'heroId' => (string) ($data['heroId'] ?? ''),
                'albumId' => (string) ($data['albumId'] ?? ''),
                'nombre' => (string) ($data['nombre'] ?? ($data['name'] ?? '')),
                'slug' => (string) ($data['slug'] ?? Slugger::slugify((string) ($data['nombre'] ?? $data['name'] ?? ''))),
                'contenido' => (string) ($data['contenido'] ?? ($data['content'] ?? '')),
                'imagen' => (string) ($data['imagen'] ?? ($data['image'] ?? '')),
                'createdAt' => (string) ($data['createdAt'] ?? ''),
                'updatedAt' => (string) ($data['updatedAt'] ?? ''),
            ],
            $decoded
        );
    }

    /**
     * @param array<int, array{heroId: string, albumId: string, nombre: string, slug: string, contenido: string, imagen: string, createdAt: string, updatedAt: string}> $records
     */
    private function persistRecords(array $records): void
    {
        file_put_contents(
            $this->storagePath,
            json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    private function ensureStorage(): void
    {
        $directory = dirname($this->storagePath);

        if ($directory !== '' && !is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        if (!is_file($this->storagePath)) {
            file_put_contents($this->storagePath, "[]");
        }
    }
}
