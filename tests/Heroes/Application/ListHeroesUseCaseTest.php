<?php

declare(strict_types=1);

namespace Tests\Heroes\Application;

use App\Heroes\Application\UseCase\ListHeroesUseCase;
use App\Heroes\Domain\Entity\Hero;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemoryHeroRepository;

final class ListHeroesUseCaseTest extends TestCase
{
    public function testItListsHeroesByAlbum(): void
    {
        $repository = new InMemoryHeroRepository();

        $heroA = Hero::create('hero-1', 'album-1', 'Iron Man', 'Genius', 'https://example.com/iron.jpg');
        $heroB = Hero::create('hero-2', 'album-1', 'Captain America', 'Leader', 'https://example.com/cap.jpg');
        $heroC = Hero::create('hero-3', 'album-2', 'Thor', 'Thunder', 'https://example.com/thor.jpg');

        $repository->seed($heroA, $heroB, $heroC);

        $useCase = new ListHeroesUseCase($repository);
        $result = $useCase->execute('album-1');

        self::assertCount(2, $result);
        $names = array_column($result, 'nombre');
        self::assertSame(['Iron Man', 'Captain America'], $names);
    }
}
