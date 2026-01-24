<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Application\UseCase;

use App\Albums\Domain\Entity\Album;
use App\Albums\Domain\Repository\AlbumRepository;
use App\Heroes\Domain\Entity\Hero;
use App\Heroes\Domain\Repository\HeroRepository;
use App\Shared\Application\UseCase\ResetDemoDataUseCase;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

final class ResetDemoDataUseCaseTest extends TestCase
{
    private AlbumRepository&MockObject $albumRepository;
    private HeroRepository&MockObject $heroRepository;
    private ResetDemoDataUseCase $useCase;

    protected function setUp(): void
    {
        $this->albumRepository = $this->createMock(AlbumRepository::class);
        $this->heroRepository = $this->createMock(HeroRepository::class);
        $this->useCase = new ResetDemoDataUseCase(
            $this->albumRepository,
            $this->heroRepository
        );
    }

    public function testExecuteDeletesExistingDataAndRestoresSeedData(): void
    {
        // Arrange: existing albums and heroes
        $existingAlbum = Album::fromPrimitives([
            'albumId' => 'existing-album-id',
            'nombre' => 'Existing Album',
            'coverImage' => null,
            'createdAt' => '2025-01-01T00:00:00+00:00',
            'updatedAt' => '2025-01-01T00:00:00+00:00',
        ]);

        $existingHero = Hero::fromPrimitives([
            'heroId' => 'existing-hero-id',
            'albumId' => 'existing-album-id',
            'nombre' => 'Existing Hero',
            'slug' => 'existing-hero',
            'contenido' => 'Test content',
            'imagen' => 'https://example.com/hero.jpg',
            'createdAt' => '2025-01-01T00:00:00+00:00',
            'updatedAt' => '2025-01-01T00:00:00+00:00',
        ]);

        // Expect: all() called to get existing data
        $this->albumRepository
            ->expects($this->atLeastOnce())
            ->method('all')
            ->willReturn([$existingAlbum]);

        $this->heroRepository
            ->expects($this->once())
            ->method('byAlbum')
            ->with('existing-album-id')
            ->willReturn([$existingHero]);

        // Expect: delete called for existing data
        $this->heroRepository
            ->expects($this->once())
            ->method('delete')
            ->with('existing-hero-id');

        $this->albumRepository
            ->expects($this->once())
            ->method('delete')
            ->with('existing-album-id');

        // Expect: save called for seed data (6 albums, 36 heroes)
        $this->albumRepository
            ->expects($this->exactly(6))
            ->method('save');

        $this->heroRepository
            ->expects($this->exactly(36))
            ->method('save');

        // Act
        $result = $this->useCase->execute();

        // Assert
        $this->assertArrayHasKey('albums', $result);
        $this->assertArrayHasKey('heroes', $result);
        $this->assertSame(6, $result['albums']);
        $this->assertSame(36, $result['heroes']);
    }

    public function testExecuteReturnsCorrectCounts(): void
    {
        // Arrange: no existing data
        $this->albumRepository
            ->expects($this->atLeastOnce())
            ->method('all')
            ->willReturn([]);

        // Act
        $result = $this->useCase->execute();

        // Assert
        $this->assertSame(6, $result['albums']);
        $this->assertSame(36, $result['heroes']);
    }
}
