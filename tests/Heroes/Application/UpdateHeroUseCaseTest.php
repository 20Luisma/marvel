<?php

declare(strict_types=1);

namespace Tests\Heroes\Application;

use App\Heroes\Application\DTO\UpdateHeroRequest;
use App\Heroes\Application\UseCase\UpdateHeroUseCase;
use App\Heroes\Domain\Entity\Hero;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemoryHeroRepository;

final class UpdateHeroUseCaseTest extends TestCase
{
    public function testItUpdatesHeroNameContentAndImage(): void
    {
        $hero = Hero::create('hero-1', 'album-1', 'Iron Man', 'Genius', 'https://example.com/iron.jpg');
        $repository = new InMemoryHeroRepository();
        $repository->save($hero);

        $useCase = new UpdateHeroUseCase($repository);
        $response = $useCase->execute(new UpdateHeroRequest(
            'hero-1',
            'Rescue',
            'Suit upgrade',
            'https://example.com/rescue.jpg'
        ));

        $updated = $repository->find('hero-1');
        self::assertNotNull($updated);
        self::assertSame('Rescue', $updated->nombre());
        self::assertSame('Suit upgrade', $updated->contenido());
        self::assertSame('https://example.com/rescue.jpg', $updated->imagen());
        self::assertSame('Rescue', $response->toArray()['nombre']);
    }

    public function testItFailsWhenHeroDoesNotExist(): void
    {
        $repository = new InMemoryHeroRepository();
        $useCase = new UpdateHeroUseCase($repository);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El héroe indicado no existe.');

        $useCase->execute(new UpdateHeroRequest('missing', 'Name', null, null));
    }

    public function testItRejectsEmptyImage(): void
    {
        $hero = Hero::create('hero-2', 'album-1', 'Captain Marvel', 'Pilot', 'https://example.com/cm.jpg');
        $repository = new InMemoryHeroRepository();
        $repository->save($hero);

        $useCase = new UpdateHeroUseCase($repository);

        $this->expectException(InvalidArgumentException::class);
        $useCase->execute(new UpdateHeroRequest('hero-2', null, null, ''));
    }
}
