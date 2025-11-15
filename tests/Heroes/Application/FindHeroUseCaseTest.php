<?php

declare(strict_types=1);

namespace Tests\Heroes\Application;

use App\Heroes\Application\UseCase\FindHeroUseCase;
use App\Heroes\Domain\Entity\Hero;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemoryHeroRepository;

final class FindHeroUseCaseTest extends TestCase
{
    public function testItReturnsHeroWhenExists(): void
    {
        $repository = new InMemoryHeroRepository();
        $hero = Hero::create('hero-1', 'album-1', 'Iron Man', 'Genius', 'https://example.com/iron.jpg');
        $repository->save($hero);

        $useCase = new FindHeroUseCase($repository);
        $result = $useCase->execute('hero-1');

        self::assertSame('hero-1', $result['heroId']);
        self::assertSame('Iron Man', $result['nombre']);
        self::assertSame('album-1', $result['albumId']);
    }

    public function test_it_throws_exception_when_hero_not_found(): void
    {
        $repository = new InMemoryHeroRepository();
        $useCase = new FindHeroUseCase($repository);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Héroe no encontrado.');

        $useCase->execute('missing-hero');
    }

    public function testItFailsWhenHeroIsMissing(): void
    {
        $repository = new InMemoryHeroRepository();
        $useCase = new FindHeroUseCase($repository);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Héroe no encontrado.');

        $useCase->execute('unknown-hero');
    }
}
