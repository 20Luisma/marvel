<?php

declare(strict_types=1);

namespace App\Albums\Infrastructure\Persistence;

use App\Albums\Domain\Entity\Album;
use App\Albums\Domain\Repository\AlbumRepository;
use DateTimeInterface;
use PDO;

final class DbAlbumRepository implements AlbumRepository
{
    private string $tablePrefix;
    private string $tableAlbums;

    public function __construct(private readonly PDO $pdo, ?string $tablePrefix = null)
    {
        $this->tablePrefix = $this->sanitizePrefix($tablePrefix);
        $this->tableAlbums = sprintf('`%salbums`', $this->tablePrefix);
    }

    public function save(Album $album): void
    {
        $sql = <<<SQL
            INSERT INTO {$this->tableAlbums} (album_id, nombre, cover_image, created_at, updated_at)
            VALUES (:album_id, :nombre, :cover_image, :created_at, :updated_at)
            ON DUPLICATE KEY UPDATE
                nombre = VALUES(nombre),
                cover_image = VALUES(cover_image),
                updated_at = VALUES(updated_at)
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'album_id' => $album->albumId(),
            'nombre' => $album->nombre(),
            'cover_image' => $album->coverImage(),
            'created_at' => $this->formatDate($album->createdAt()),
            'updated_at' => $this->formatDate($album->updatedAt()),
        ]);
    }

    /**
     * @return array<int, Album>
     */
    public function all(): array
    {
        $stmt = $this->pdo->query("SELECT album_id, nombre, cover_image, created_at, updated_at FROM {$this->tableAlbums} ORDER BY created_at ASC");

        $rows = $stmt->fetchAll();

        return array_map(fn (array $row): Album => $this->hydrate($row), $rows);
    }

    public function find(string $albumId): ?Album
    {
        $stmt = $this->pdo->prepare("SELECT album_id, nombre, cover_image, created_at, updated_at FROM {$this->tableAlbums} WHERE album_id = :album_id LIMIT 1");
        $stmt->execute(['album_id' => $albumId]);

        $row = $stmt->fetch();

        return $row !== false ? $this->hydrate($row) : null;
    }

    public function delete(string $albumId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->tableAlbums} WHERE album_id = :album_id");
        $stmt->execute(['album_id' => $albumId]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Album
    {
        return Album::fromPrimitives([
            'albumId' => (string) ($row['album_id'] ?? ''),
            'nombre' => (string) ($row['nombre'] ?? ''),
            'coverImage' => isset($row['cover_image']) ? (string) $row['cover_image'] : null,
            'createdAt' => (string) ($row['created_at'] ?? ''),
            'updatedAt' => (string) ($row['updated_at'] ?? ''),
        ]);
    }

    private function formatDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s.u');
    }

    private function sanitizePrefix(?string $prefix): string
    {
        $source = $prefix;

        if ($source === null) {
            $envPrefix = $_ENV['DB_TABLE_PREFIX'] ?? getenv('DB_TABLE_PREFIX');
            $source = is_string($envPrefix) ? $envPrefix : '';
        }

        $clean = preg_replace('/[^A-Za-z0-9_]/', '', (string) $source) ?? '';

        return $clean;
    }
}
