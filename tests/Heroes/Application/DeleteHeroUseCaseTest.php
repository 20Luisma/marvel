<?php

declare(strict_types=1);

namespace Tests\Heroes\Application;

use App\Heroes\Application\UseCase\DeleteHeroUseCase;
use App\Heroes\Domain\Entity\Hero;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemoryHeroRepository;

final class DeleteHeroUseCaseTest extends TestCase
{
    public function testItDeletesHeroSuccessfully(): void
    {
        $heroRepository = new InMemoryHeroRepository();

        $hero = Hero::create('hero-1', 'album-1', 'Test Hero', 'Contenido', 'img.jpg');
        $heroRepository->seed($hero);

        $useCase = new DeleteHeroUseCase($heroRepository);
        $useCase->execute('hero-1');

        self::assertNull($heroRepository->find('hero-1'));
    }

    public function testItFailsWhenHeroDoesNotExist(): void
    {
        $heroRepository = new InMemoryHeroRepository();

        $this->expectException(InvalidArgumentException::class);

        $useCase = new DeleteHeroUseCase($heroRepository);
        $useCase->execute('non-existent-hero');
    }
}
