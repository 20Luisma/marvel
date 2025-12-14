<?php

declare(strict_types=1);

namespace Tests\Heroes\Domain;

use App\Heroes\Domain\Entity\Hero;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Tests de excepciones para la entidad Hero.
 * 
 * Valida invariantes de dominio y manejo de errores controlados.
 */
final class HeroExceptionsTest extends TestCase
{
    /**
     * @dataProvider invalidIdProvider
     */
    public function testFromPrimitivesWithInvalidHeroIdThrowsException(string $heroId): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El id del héroe no puede estar vacío.');

        Hero::fromPrimitives([
            'heroId' => $heroId,
            'albumId' => 'album-1',
            'slug' => 'spider-man',
            'createdAt' => '2025-01-01T00:00:00+00:00',
            'updatedAt' => '2025-01-01T00:00:00+00:00',
            'nombre' => 'Spider-Man',
            'contenido' => 'Contenido',
            'imagen' => 'image.jpg',
        ]);
    }

    /**
     * @dataProvider invalidIdProvider
     */
    public function testFromPrimitivesWithInvalidAlbumIdThrowsException(string $albumId): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El id del álbum no puede estar vacío.');

        Hero::fromPrimitives([
            'heroId' => 'hero-1',
            'albumId' => $albumId,
            'slug' => 'spider-man',
            'createdAt' => '2025-01-01T00:00:00+00:00',
            'updatedAt' => '2025-01-01T00:00:00+00:00',
            'nombre' => 'Spider-Man',
            'contenido' => 'Contenido',
            'imagen' => 'image.jpg',
        ]);
    }

    /**
     * @dataProvider invalidIdProvider
     */
    public function testCreateWithInvalidHeroIdThrowsException(string $heroId): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El id del héroe no puede estar vacío');

        Hero::create($heroId, 'album-1', 'Spider-Man', 'Contenido', 'image.jpg');
    }

    /**
     * @dataProvider invalidIdProvider
     */
    public function testCreateWithInvalidAlbumIdThrowsException(string $albumId): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El id del álbum no puede estar vacío');

        Hero::create('hero-123', $albumId, 'Spider-Man', 'Contenido', 'image.jpg');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidIdProvider(): array
    {
        return [
            'empty' => [''],
            'spaces' => ['   '],
            'tabs-newlines' => ["\t\n  "],
        ];
    }

    public function testCreateWithEmptyNameThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El nombre del héroe no puede estar vacío');
        
        Hero::create('hero-123', 'album-1', '', 'Contenido', 'image.jpg');
    }

    public function testCreateWithWhitespaceOnlyNameThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El nombre del héroe no puede estar vacío');
        
        Hero::create('hero-456', 'album-1', '   ', 'Contenido', 'image.jpg');
    }

    public function testCreateWithEmptyImageThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La imagen del héroe no puede estar vacía');
        
        Hero::create('hero-789', 'album-1', 'Spider-Man', 'Contenido', '');
    }

    public function testCreateWithWhitespaceOnlyImageThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La imagen del héroe no puede estar vacía');
        
        Hero::create('hero-101', 'album-1', 'Iron Man', 'Descripción', '   ');
    }

    public function testRenameToEmptyNameThrowsException(): void
    {
        $hero = Hero::create('hero-200', 'album-1', 'Thor', 'Dios del trueno', 'thor.jpg');
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El nombre del héroe no puede estar vacío');
        
        $hero->rename('');
    }

    public function testChangeImageToEmptyThrowsException(): void
    {
        $hero = Hero::create('hero-300', 'album-1', 'Hulk', 'El más fuerte', 'hulk.jpg');
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La imagen del héroe no puede estar vacía');
        
        $hero->changeImage('');
    }

    public function testSlugIsGeneratedCorrectly(): void
    {
        $hero = Hero::create('hero-400', 'album-1', 'Captain Marvel', 'La piloto más poderosa', 'cap.jpg');
        
        $this->assertSame('captain-marvel', $hero->slug());
    }

    public function testRenameUpdatesSlug(): void
    {
        $hero = Hero::create('hero-500', 'album-1', 'Original Name', 'Content', 'img.jpg');
        $originalSlug = $hero->slug();
        
        $hero->rename('New Different Name');
        
        $this->assertNotSame($originalSlug, $hero->slug());
        $this->assertSame('new-different-name', $hero->slug());
    }

    public function testValidHeroDoesNotThrow(): void
    {
        $hero = Hero::create(
            'hero-valid',
            'album-marvel',
            'Black Widow',
            'Natasha Romanoff es una espía',
            'black-widow.jpg'
        );
        
        $this->assertSame('hero-valid', $hero->heroId());
        $this->assertSame('album-marvel', $hero->albumId());
        $this->assertSame('Black Widow', $hero->nombre());
        $this->assertSame('black-widow.jpg', $hero->imagen());
    }
}
