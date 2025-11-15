<?php

declare(strict_types=1);

namespace Tests\Heroes;

use App\Albums\Application\DTO\CreateAlbumRequest;
use App\Albums\Application\UseCase\CreateAlbumUseCase;
use App\Dev\Seed\SeedHeroesService;
use App\Heroes\Application\UseCase\CreateHeroUseCase;
use App\Shared\Infrastructure\Bus\InMemoryEventBus;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemoryAlbumRepository;
use Tests\Fakes\FakeHeroRepository;

final class SeedHeroesServiceTest extends TestCase
{
    public function test_seed_force_populates_fake_repository_and_skips_duplicates(): void
    {
        $albumRepository = new InMemoryAlbumRepository();
        $heroRepository = new FakeHeroRepository();
        $eventBus = new InMemoryEventBus();
        $createHeroUseCase = new CreateHeroUseCase($heroRepository, $albumRepository, $eventBus);
        $seedService = new SeedHeroesService($albumRepository, $heroRepository, $createHeroUseCase);

        $createAlbumUseCase = new CreateAlbumUseCase($albumRepository);
        $albumResponse = $createAlbumUseCase->execute(new CreateAlbumRequest('Avengers', null));
        $albumId = $albumResponse->toArray()['albumId'];

        $created = $seedService->seedForce();

        $storedHeroes = $heroRepository->findAll();

        self::assertSame(5, $created);
        self::assertCount(5, $storedHeroes);
        self::assertNotEmpty($storedHeroes);
        self::assertArrayHasKey('nombre', $storedHeroes[0]);
        self::assertArrayHasKey('imagen', $storedHeroes[0]);
        self::assertArrayHasKey('contenido', $storedHeroes[0]);
        self::assertContains($albumId, array_column($storedHeroes, 'albumId'));

        $createdAgain = $seedService->seedForce();

        self::assertSame(0, $createdAgain);
        self::assertCount(5, $heroRepository->findAll());
    }
}
