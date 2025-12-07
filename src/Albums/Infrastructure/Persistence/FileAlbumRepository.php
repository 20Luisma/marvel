<?php

declare(strict_types=1);

namespace App\Albums\Infrastructure\Persistence;

use App\Albums\Domain\Entity\Album;
use App\Albums\Domain\Repository\AlbumRepository;

final class FileAlbumRepository implements AlbumRepository
{
    public function __construct(private readonly string $storagePath)
    {
        $this->ensureStorage();
    }

    public function save(Album $album): void
    {
        $records = $this->loadRecords();
        $records = array_values(array_filter(
            $records,
            static fn (array $data): bool => $data['albumId'] !== $album->albumId()
        ));

        $records[] = $album->toPrimitives();

        usort(
            $records,
            static fn (array $a, array $b): int => strcmp($a['createdAt'], $b['createdAt'])
        );

        $this->persistRecords($records);
    }

    /**
     * @return array<int, Album>
     */
    public function all(): array
    {
        return array_map(
            static fn (array $data): Album => Album::fromPrimitives($data),
            $this->loadRecords()
        );
    }

    public function find(string $albumId): ?Album
    {
        foreach ($this->loadRecords() as $data) {
            if ($data['albumId'] === $albumId) {
                return Album::fromPrimitives($data);
            }
        }

        return null;
    }

    public function delete(string $albumId): void
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
     * @return array<int, array{albumId: string, nombre: string, coverImage: ?string, createdAt: string, updatedAt: string}>
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
                'albumId' => (string) ($data['albumId'] ?? ''),
                'nombre' => (string) ($data['nombre'] ?? ($data['name'] ?? '')),
                'coverImage' => isset($data['coverImage'])
                    ? (string) $data['coverImage']
                    : (isset($data['cover_image']) ? (string) $data['cover_image'] : null),
                'createdAt' => (string) ($data['createdAt'] ?? ''),
                'updatedAt' => (string) ($data['updatedAt'] ?? ($data['createdAt'] ?? '')),
            ],
            $decoded
        );
    }

    /**
     * @param array<int, array{albumId: string, nombre: string, coverImage: ?string, createdAt: string, updatedAt: string}> $records
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
