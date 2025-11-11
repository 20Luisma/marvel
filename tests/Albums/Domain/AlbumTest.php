<?php

declare(strict_types=1);

namespace Tests\Albums\Domain;

use App\Albums\Domain\Entity\Album;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AlbumTest extends TestCase
{
    public function testItCreatesAlbumWithNombre(): void
    {
        $album = Album::create('album-1', 'Marvel Legends');

        self::assertSame('album-1', $album->albumId());
        self::assertSame('Marvel Legends', $album->nombre());
        self::assertNull($album->coverImage());
        self::assertNotNull($album->createdAt());
        self::assertNotNull($album->updatedAt());

        $primitives = $album->toPrimitives();
        self::assertSame('Marvel Legends', $primitives['nombre']);
        self::assertArrayHasKey('coverImage', $primitives);
        self::assertNull($primitives['coverImage']);
    }

    public function testItCreatesAlbumWithCoverImage(): void
    {
        $album = Album::create('album-2', 'X-Men', 'https://example.com/cover.jpg');

        self::assertSame('https://example.com/cover.jpg', $album->coverImage());

        $primitives = $album->toPrimitives();
        self::assertSame('https://example.com/cover.jpg', $primitives['coverImage']);
    }

    public function testItRestoresAlbumFromLegacyData(): void
    {
        $album = Album::fromPrimitives([
            'albumId' => 'album-legacy',
            'name' => 'Legacy Album',
            'createdAt' => '2024-01-01T10:00:00+00:00',
            'updatedAt' => '2024-02-01T10:00:00+00:00',
            'cover_image' => 'https://example.com/legacy.jpg',
        ]);

        self::assertSame('Legacy Album', $album->nombre());
        self::assertSame('album-legacy', $album->albumId());
        self::assertSame('https://example.com/legacy.jpg', $album->coverImage());
    }

    public function testItUpdatesCoverImage(): void
    {
        $album = Album::create('album-4', 'Avengers');
        $originalUpdatedAt = $album->updatedAt();

        $album->actualizarCover('https://example.com/avengers.jpg');

        self::assertSame('https://example.com/avengers.jpg', $album->coverImage());
        self::assertGreaterThan($originalUpdatedAt, $album->updatedAt());
    }

    public function testItFailsWithEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Album::create('album-3', ' ');
    }
}
