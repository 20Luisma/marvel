<?php

declare(strict_types=1);

namespace Tests\Unit\Heroes;

use App\Heroes\Domain\Entity\Hero;
use App\Heroes\Infrastructure\Persistence\FileHeroRepository;
use PHPUnit\Framework\TestCase;

final class FileHeroRepositoryTest extends TestCase
{
    private string $filePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filePath = __DIR__ . '/../../tmp/unit_heroes_test.json';
        if (is_file($this->filePath)) {
            unlink($this->filePath);
        }
    }

    public function testItPersistsAndRetrievesHeroes(): void
    {
        $repository = new FileHeroRepository($this->filePath);
        $hero = Hero::create('hero-1', 'album-1', 'Black Widow', 'Spy', 'https://example.com/blackwidow.jpg');

        $repository->save($hero);

        $stored = $repository->find('hero-1');
        self::assertNotNull($stored);
        self::assertSame('Black Widow', $stored->nombre());

        $collection = $repository->byAlbum('album-1');
        self::assertCount(1, $collection);
        self::assertSame('hero-1', $collection[0]->heroId());
    }

    public function testItDeletesHeroesByAlbum(): void
    {
        $repository = new FileHeroRepository($this->filePath);
        $heroA = Hero::create('hero-1', 'album-1', 'Hawkeye', 'Archer', 'https://example.com/hawkeye.jpg');
        $heroB = Hero::create('hero-2', 'album-1', 'Falcon', 'Flyer', 'https://example.com/falcon.jpg');
        $repository->save($heroA);
        $repository->save($heroB);

        $repository->deleteByAlbum('album-1');

        self::assertNull($repository->find('hero-1'));
        self::assertNull($repository->find('hero-2'));
        self::assertCount(0, $repository->byAlbum('album-1'));
    }

    protected function tearDown(): void
    {
        if (is_file($this->filePath)) {
            unlink($this->filePath);
        }

        parent::tearDown();
    }
}
