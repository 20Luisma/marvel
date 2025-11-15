<?php

declare(strict_types=1);

namespace Tests\Application;

use App\Albums\Application\DTO\CreateAlbumRequest;
use App\Albums\Application\UseCase\CreateAlbumUseCase;
use App\Dev\Seed\SeedHeroesService;
use App\Heroes\Application\DTO\CreateHeroRequest as CreateHeroRequestDto;
use App\Heroes\Application\UseCase\CreateHeroUseCase;
use App\Shared\Infrastructure\Bus\InMemoryEventBus;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemoryAlbumRepository;
use Tests\Doubles\InMemoryHeroRepository;

class SeedHeroesServiceTest extends TestCase
{
    private InMemoryAlbumRepository $albumRepository;
    private InMemoryHeroRepository $heroRepository;
    private SeedHeroesService $seedService;
    private array $container;

    protected function setUp(): void
    {
        parent::setUp();
        $bootstrapContainer = require_once dirname(__DIR__, 2) . '/src/bootstrap.php';
        /** @var array<string, mixed> $resolvedContainer */
        $resolvedContainer = is_array($bootstrapContainer)
            ? $bootstrapContainer
            : ($GLOBALS['__clean_marvel_container'] ?? []);
        $this->container = $resolvedContainer;

        $this->albumRepository = new InMemoryAlbumRepository();
        $this->heroRepository = new InMemoryHeroRepository();

        $eventBus = new InMemoryEventBus();

        $this->container['albumRepository'] = $this->albumRepository;
        $this->container['heroRepository'] = $this->heroRepository;
        $this->container['eventBus'] = $eventBus;

        $this->container['useCases']['createAlbum'] = new CreateAlbumUseCase($this->albumRepository);
        $this->container['useCases']['createHero'] = new CreateHeroUseCase($this->heroRepository, $this->albumRepository, $eventBus);

        // @todo Provide a HeroRepository::all()/count port so SeedHeroesService can avoid manual album iteration.
        $this->seedService = new SeedHeroesService(
            $this->albumRepository,
            $this->heroRepository,
            $this->container['useCases']['createHero']
        );
    }

    private function createAlbum(string $name): string
    {
        $response = $this->container['useCases']['createAlbum']->execute(new CreateAlbumRequest($name, null));

        return $response->toArray()['albumId'];
    }

    public function testSeedIfEmptyCreatesHeroesForExistingAlbums(): void
    {
        $avengersId = $this->createAlbum('Avengers');
        $xmenId = $this->createAlbum('X-Men');

        $this->seedService->seedIfEmpty();

        $this->assertCount(5, $this->heroRepository->byAlbum($avengersId));
        $this->assertCount(5, $this->heroRepository->byAlbum($xmenId));
    }

    public function testSeedIfEmptyDoesNothingIfHeroesExist(): void
    {
        $albumId = $this->createAlbum('Avengers');
        $useCase = $this->container['useCases']['createHero'];
        $useCase->execute(new CreateHeroRequestDto($albumId, 'Some Hero', 'Contenido', 'https://example.com/hero.jpg'));

        $this->seedService->seedIfEmpty();

        $this->assertCount(1, $this->heroRepository->byAlbum($albumId));
    }

    public function testSeedForceAddsMissingHeroesWithoutDuplicating(): void
    {
        $albumId = $this->createAlbum('Avengers');

        // Pre-seed one hero
        $useCase = $this->container['useCases']['createHero'];
        $useCase->execute(new CreateHeroRequestDto($albumId, 'Iron Man', 'Contenido', 'https://example.com/iron.jpg'));

        $this->assertCount(1, $this->heroRepository->byAlbum($albumId));

        $createdCount = $this->seedService->seedForce();

        $this->assertEquals(4, $createdCount);
        $this->assertCount(5, $this->heroRepository->byAlbum($albumId));
    }
}
