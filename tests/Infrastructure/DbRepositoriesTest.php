<?php

declare(strict_types=1);

namespace Tests\Infrastructure;

use App\Albums\Infrastructure\Persistence\DbAlbumRepository;
use App\Heroes\Infrastructure\Persistence\DbHeroRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class DbRepositoriesTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('PRAGMA foreign_keys = ON');

        $this->createSchema();
        $this->seedData();
    }

    public function testAlbumAndHeroRepositoriesReturnSeededData(): void
    {
        $albumRepo = new DbAlbumRepository($this->pdo);
        $heroRepo = new DbHeroRepository($this->pdo);

        self::assertCount(6, $albumRepo->all());
        self::assertCount(18, $heroRepo->all());
    }

    private function createSchema(): void
    {
        $this->pdo->exec(
            'CREATE TABLE albums (
                album_id TEXT PRIMARY KEY,
                nombre TEXT NOT NULL,
                cover_image TEXT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );

        $this->pdo->exec(
            'CREATE TABLE heroes (
                hero_id TEXT PRIMARY KEY,
                album_id TEXT NOT NULL,
                nombre TEXT NOT NULL,
                slug TEXT NOT NULL,
                contenido TEXT NOT NULL,
                imagen TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                FOREIGN KEY (album_id) REFERENCES albums(album_id) ON DELETE CASCADE
            )'
        );
    }

    private function seedData(): void
    {
        $now = '2024-01-01 10:00:00.000000';

        $albumStmt = $this->pdo->prepare(
            'INSERT INTO albums (album_id, nombre, cover_image, created_at, updated_at) 
             VALUES (:album_id, :nombre, :cover_image, :created_at, :updated_at)'
        );

        for ($i = 1; $i <= 6; $i++) {
            $albumId = sprintf('album-%02d-id', $i);
            $albumStmt->execute([
                'album_id' => $albumId,
                'nombre' => sprintf('Album %02d', $i),
                'cover_image' => sprintf('https://example.com/cover-%02d.jpg', $i),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $heroStmt = $this->pdo->prepare(
            'INSERT INTO heroes (hero_id, album_id, nombre, slug, contenido, imagen, created_at, updated_at) 
             VALUES (:hero_id, :album_id, :nombre, :slug, :contenido, :imagen, :created_at, :updated_at)'
        );

        for ($albumIndex = 1; $albumIndex <= 6; $albumIndex++) {
            $albumId = sprintf('album-%02d-id', $albumIndex);
            for ($heroIndex = 1; $heroIndex <= 3; $heroIndex++) {
                $heroName = sprintf('Hero %02d-%02d', $albumIndex, $heroIndex);
                $heroStmt->execute([
                    'hero_id' => sprintf('hero-%02d-%02d-id', $albumIndex, $heroIndex),
                    'album_id' => $albumId,
                    'nombre' => $heroName,
                    'slug' => strtolower(str_replace(' ', '-', $heroName)),
                    'contenido' => 'Demo hero content',
                    'imagen' => sprintf('https://example.com/hero-%02d-%02d.jpg', $albumIndex, $heroIndex),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
