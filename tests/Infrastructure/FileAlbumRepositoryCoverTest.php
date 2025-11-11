<?php

declare(strict_types=1);

namespace Tests\Infrastructure;

use App\Albums\Domain\Entity\Album;
use App\Albums\Infrastructure\Persistence\FileAlbumRepository;
use PHPUnit\Framework\TestCase;

final class FileAlbumRepositoryCoverTest extends TestCase
{
    private string $filePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filePath = __DIR__ . '/../tmp/file_album_cover_test.json';
        if (is_file($this->filePath)) {
            unlink($this->filePath);
        }
    }

    public function testItPersistsCoverImage(): void
    {
        $cover = 'https://example.com/cover.jpg';
        $repository = new FileAlbumRepository($this->filePath);
        $album = Album::create('album-cover', 'Portadas', $cover);
        $repository->save($album);

        $stored = $repository->find('album-cover');
        self::assertNotNull($stored);
        self::assertSame($cover, $stored->coverImage());

        $reloadedRepository = new FileAlbumRepository($this->filePath);
        $reloaded = $reloadedRepository->find('album-cover');
        self::assertNotNull($reloaded);
        self::assertSame($cover, $reloaded->coverImage());
    }

    protected function tearDown(): void
    {
        if (is_file($this->filePath)) {
            unlink($this->filePath);
        }

        parent::tearDown();
    }
}
