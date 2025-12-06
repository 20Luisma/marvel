<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;
use PDOStatement;

/**
 * PDO adapter that rewrites MySQL-style upserts into SQLite-compatible syntax for tests.
 */
final class SqliteMySqlUpsertPdo extends PDO
{
    public function __construct(string $dsn = 'sqlite::memory:')
    {
        parent::__construct($dsn);
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    /**
     * @param array<int|string, mixed> $options
     */
    public function prepare(string $statement, array $options = []): PDOStatement|false
    {
        $statement = $this->rewriteMySqlUpsert($statement);

        return parent::prepare($statement, $options);
    }

    private function rewriteMySqlUpsert(string $sql): string
    {
        if (!str_contains($sql, 'ON DUPLICATE KEY')) {
            return $sql;
        }

        if (preg_match('/INSERT\\s+INTO\\s+`?([A-Za-z0-9_]+)`?\\s*\\(/i', $sql, $matches) !== 1) {
            return $sql;
        }

        $table = $matches[1];

        if (str_ends_with($table, 'albums')) {
            return <<<SQL
INSERT INTO `{$table}` (album_id, nombre, cover_image, created_at, updated_at)
VALUES (:album_id, :nombre, :cover_image, :created_at, :updated_at)
ON CONFLICT(album_id) DO UPDATE SET
    nombre = excluded.nombre,
    cover_image = excluded.cover_image,
    updated_at = excluded.updated_at
SQL;
        }

        if (str_ends_with($table, 'heroes')) {
            return <<<SQL
INSERT INTO `{$table}` (hero_id, album_id, nombre, slug, contenido, imagen, created_at, updated_at)
VALUES (:hero_id, :album_id, :nombre, :slug, :contenido, :imagen, :created_at, :updated_at)
ON CONFLICT(hero_id) DO UPDATE SET
    nombre = excluded.nombre,
    slug = excluded.slug,
    contenido = excluded.contenido,
    imagen = excluded.imagen,
    updated_at = excluded.updated_at
SQL;
        }

        return $sql;
    }
}
