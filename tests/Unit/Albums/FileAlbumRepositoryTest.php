<?php

declare(strict_types=1);

namespace Tests\Unit\Albums;

use App\Albums\Domain\Entity\Album;
use App\Albums\Infrastructure\Persistence\FileAlbumRepository;
use PHPUnit\Framework\TestCase;

final class FileAlbumRepositoryTest extends TestCase
{
    private string $filePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filePath = __DIR__ . '/../../tmp/unit_albums_test.json';
        if (is_file($this->filePath)) {
            unlink($this->filePath);
        }
    }

    public function testItPersistsAndLoadsAlbums(): void
    {
        $repository = new FileAlbumRepository($this->filePath);
        $album = Album::create('album-1', 'Guardianes');
        $repository->save($album);

        $stored = $repository->find('album-1');
        self::assertNotNull($stored);
        self::assertSame('Guardianes', $stored->nombre());

        $all = $repository->all();
        self::assertCount(1, $all);
        self::assertSame('Guardianes', $all[0]->nombre());
    }

    public function testItDeletesAlbums(): void
    {
        $repository = new FileAlbumRepository($this->filePath);
        $album = Album::create('album-2', 'Avengers');
        $repository->save($album);
        self::assertNotNull($repository->find('album-2'));

        $repository->delete('album-2');

        self::assertNull($repository->find('album-2'));
        self::assertCount(0, $repository->all());
    }

    protected function tearDown(): void
    {
        if (is_file($this->filePath)) {
            unlink($this->filePath);
        }

        parent::tearDown();
    }
}
