<?php

declare(strict_types=1);

namespace Tests\Heroes\Infrastructure;

use App\Heroes\Domain\Entity\Hero;
use App\Heroes\Infrastructure\Persistence\FileHeroRepository;
use PHPUnit\Framework\TestCase;

final class FileHeroRepositoryTest extends TestCase
{
    private string $filePath;

    protected function setUp(): void
    {
        $this->filePath = tempnam(sys_get_temp_dir(), 'hero_repo_') ?: sys_get_temp_dir() . '/hero_repo_' . uniqid();
        file_put_contents($this->filePath, '[]');
    }

    protected function tearDown(): void
    {
        if (is_file($this->filePath)) {
            unlink($this->filePath);
        }
    }

    public function testAllNormalizesLegacyRecordsAndSortsByCreationDate(): void
    {
        $legacyRecords = [
            [
                'heroId' => 'hero-2',
                'albumId' => 'album-1',
                'name' => 'Thor Odinson',
                'content' => 'Dios del trueno',
                'image' => 'https://example.com/thor.jpg',
                'createdAt' => '2023-01-02T00:00:00+00:00',
                'updatedAt' => '2023-01-03T00:00:00+00:00',
            ],
            [
                'heroId' => 'hero-1',
                'albumId' => 'album-1',
                'nombre' => 'Iron Man',
                'contenido' => 'Genio millonario',
                'imagen' => 'https://example.com/iron.jpg',
                'createdAt' => '2023-01-01T00:00:00+00:00',
                'updatedAt' => '2023-01-01T12:00:00+00:00',
            ],
        ];
        file_put_contents($this->filePath, json_encode($legacyRecords, JSON_PRETTY_PRINT));

        $repository = new FileHeroRepository($this->filePath);
        $heroes = $repository->all();

        self::assertCount(2, $heroes);
        self::assertSame(['hero-1', 'hero-2'], array_map(static fn (Hero $hero): string => $hero->heroId(), $heroes));
        self::assertSame('thor-odinson', $heroes[1]->slug(), 'Slug should be generated for legacy records lacking it.');
    }

    public function testSaveReplacesExistingHeroes(): void
    {
        $repository = new FileHeroRepository($this->filePath);
        $hero = Hero::create('hero-1', 'album-1', 'Spider-Man', 'Tu amigo', 'https://example.com/spidey.jpg');
        $repository->save($hero);

        $hero->rename('Spider-Man Actualizado');
        $repository->save($hero);

        $heroes = $repository->all();
        self::assertCount(1, $heroes);
        self::assertSame('Spider-Man Actualizado', $heroes[0]->nombre());
    }

    public function testDeleteByAlbumRemovesMatchingRecords(): void
    {
        $repository = new FileHeroRepository($this->filePath);
        $heroA = Hero::create('hero-1', 'album-1', 'Iron Man', 'Genio', 'https://example.com/iron.jpg');
        $heroB = Hero::create('hero-2', 'album-2', 'Thor', 'Trueno', 'https://example.com/thor.jpg');
        $repository->save($heroA);
        $repository->save($heroB);

        $repository->deleteByAlbum('album-1');

        $heroes = $repository->all();
        self::assertCount(1, $heroes);
        self::assertSame('hero-2', $heroes[0]->heroId());
    }

    public function testAllReturnsEmptyArrayWhenJsonInvalid(): void
    {
        file_put_contents($this->filePath, '{not-json');

        $repository = new FileHeroRepository($this->filePath);
        self::assertSame([], $repository->all());
    }
}
