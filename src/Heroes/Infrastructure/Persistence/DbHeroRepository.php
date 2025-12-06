<?php

declare(strict_types=1);

namespace App\Heroes\Infrastructure\Persistence;

use App\Heroes\Domain\Entity\Hero;
use App\Heroes\Domain\Repository\HeroRepository;
use DateTimeInterface;
use PDO;

final class DbHeroRepository implements HeroRepository
{
    private string $tablePrefix;
    private string $tableHeroes;

    public function __construct(private readonly PDO $pdo, ?string $tablePrefix = null)
    {
        $this->tablePrefix = $this->sanitizePrefix($tablePrefix);
        $this->tableHeroes = sprintf('`%sheroes`', $this->tablePrefix);
    }

    public function save(Hero $hero): void
    {
        $sql = <<<SQL
            INSERT INTO {$this->tableHeroes} (hero_id, album_id, nombre, slug, contenido, imagen, created_at, updated_at)
            VALUES (:hero_id, :album_id, :nombre, :slug, :contenido, :imagen, :created_at, :updated_at)
            ON DUPLICATE KEY UPDATE
                nombre = VALUES(nombre),
                slug = VALUES(slug),
                contenido = VALUES(contenido),
                imagen = VALUES(imagen),
                updated_at = VALUES(updated_at)
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'hero_id' => $hero->heroId(),
            'album_id' => $hero->albumId(),
            'nombre' => $hero->nombre(),
            'slug' => $hero->slug(),
            'contenido' => $hero->contenido(),
            'imagen' => $hero->imagen(),
            'created_at' => $this->formatDate($hero->createdAt()),
            'updated_at' => $this->formatDate($hero->updatedAt()),
        ]);
    }

    /**
     * @return array<int, Hero>
     */
    public function byAlbum(string $albumId): array
    {
        $stmt = $this->pdo->prepare("SELECT hero_id, album_id, nombre, slug, contenido, imagen, created_at, updated_at FROM {$this->tableHeroes} WHERE album_id = :album_id ORDER BY created_at ASC");
        $stmt->execute(['album_id' => $albumId]);

        $rows = $stmt->fetchAll();

        return array_map(fn (array $row): Hero => $this->hydrate($row), $rows);
    }

    /**
     * @return array<int, Hero>
     */
    public function all(): array
    {
        $stmt = $this->pdo->query("SELECT hero_id, album_id, nombre, slug, contenido, imagen, created_at, updated_at FROM {$this->tableHeroes} ORDER BY created_at ASC");
        $rows = $stmt->fetchAll();

        return array_map(fn (array $row): Hero => $this->hydrate($row), $rows);
    }

    public function find(string $heroId): ?Hero
    {
        $stmt = $this->pdo->prepare("SELECT hero_id, album_id, nombre, slug, contenido, imagen, created_at, updated_at FROM {$this->tableHeroes} WHERE hero_id = :hero_id LIMIT 1");
        $stmt->execute(['hero_id' => $heroId]);

        $row = $stmt->fetch();

        return $row !== false ? $this->hydrate($row) : null;
    }

    public function delete(string $heroId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->tableHeroes} WHERE hero_id = :hero_id");
        $stmt->execute(['hero_id' => $heroId]);
    }

    public function deleteByAlbum(string $albumId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->tableHeroes} WHERE album_id = :album_id");
        $stmt->execute(['album_id' => $albumId]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Hero
    {
        return Hero::fromPrimitives([
            'heroId' => (string) ($row['hero_id'] ?? ''),
            'albumId' => (string) ($row['album_id'] ?? ''),
            'nombre' => (string) ($row['nombre'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'contenido' => (string) ($row['contenido'] ?? ''),
            'imagen' => (string) ($row['imagen'] ?? ''),
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
