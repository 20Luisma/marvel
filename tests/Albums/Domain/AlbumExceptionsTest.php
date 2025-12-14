<?php

declare(strict_types=1);

namespace Tests\Albums\Domain;

use App\Albums\Domain\Entity\Album;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Tests de excepciones para la entidad Album.
 * 
 * Valida que el dominio rechaza datos inválidos de forma controlada,
 * garantizando invariantes de negocio.
 */
final class AlbumExceptionsTest extends TestCase
{
    /**
     * @dataProvider invalidAlbumIdProvider
     */
    public function testCreateWithInvalidAlbumIdThrowsException(string $albumId): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El id del álbum no puede estar vacío.');

        Album::create($albumId, 'Nombre válido', null);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidAlbumIdProvider(): array
    {
        return [
            'empty' => [''],
            'whitespace' => [" \t\n "],
            'tabs-newlines' => ["\t\n"],
        ];
    }

    public function testCreateWithEmptyNameThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El nombre del álbum no puede estar vacío.');
        
        Album::create('album-123', '', null);
    }

    public function testCreateWithWhitespaceOnlyNameThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El nombre del álbum no puede estar vacío.');
        
        Album::create('album-456', '   ', null);
    }

    public function testRenameToEmptyNameThrowsException(): void
    {
        $album = Album::create('album-789', 'Nombre válido', null);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El nombre del álbum no puede estar vacío.');
        
        $album->renombrar('');
    }

    public function testRenameToWhitespaceOnlyThrowsException(): void
    {
        $album = Album::create('album-101', 'Nombre válido', null);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El nombre del álbum no puede estar vacío.');
        
        $album->renombrar("\t\n  ");
    }

    public function testFromPrimitivesWithMissingAlbumIdThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El campo albumId es obligatorio.');
        
        Album::fromPrimitives([
            'nombre' => 'Test Album',
            'createdAt' => '2025-01-01T00:00:00+00:00',
        ]);
    }

    public function testValidAlbumDoesNotThrow(): void
    {
        $album = Album::create('album-valid', 'Marvel Heroes', 'cover.jpg');
        
        $this->assertSame('album-valid', $album->albumId());
        $this->assertSame('Marvel Heroes', $album->nombre());
        $this->assertSame('cover.jpg', $album->coverImage());
    }
}
