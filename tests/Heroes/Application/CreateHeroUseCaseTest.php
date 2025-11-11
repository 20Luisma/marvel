<?php

declare(strict_types=1);

namespace Tests\Heroes\Application;

use App\Albums\Domain\Entity\Album;
use App\Heroes\Application\DTO\CreateHeroRequest;
use App\Heroes\Application\UseCase\CreateHeroUseCase;
use App\Heroes\Domain\Event\HeroCreated;
use App\Shared\Domain\Bus\DomainEvent;
use App\Shared\Domain\Event\DomainEventHandler;
use App\Shared\Infrastructure\Bus\InMemoryEventBus;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemoryAlbumRepository;
use Tests\Doubles\InMemoryHeroRepository;

final class CreateHeroUseCaseTest extends TestCase
{
    public function testItCreatesHeroWhenAlbumExists(): void
    {
        $albumRepository = new InMemoryAlbumRepository();
        $heroRepository = new InMemoryHeroRepository();
        $eventBus = new InMemoryEventBus();

        $album = Album::create('album-1', 'Marvel');
        $albumRepository->seed($album);

        $handler = new class implements DomainEventHandler {
            public array $capturedEvents = [];

            public static function subscribedTo(): string
            {
                return HeroCreated::eventName();
            }

            public function __invoke(DomainEvent $event): void
            {
                if ($event instanceof HeroCreated) {
                    $this->capturedEvents[] = $event->toPrimitives();
                }
            }
        };
        $eventBus->subscribe($handler);

        $useCase = new CreateHeroUseCase($heroRepository, $albumRepository, $eventBus);
        $request = new CreateHeroRequest('album-1', 'Iron Man', 'Genius billionaire', 'https://example.com/iron.jpg');
        $response = $useCase->execute($request);

        self::assertSame('Iron Man', $response->toArray()['nombre']);
        self::assertCount(1, $heroRepository->byAlbum('album-1'));
        self::assertCount(1, $handler->capturedEvents);
        self::assertSame('Iron Man', $handler->capturedEvents[0]['name']);
    }

    public function testItFailsWhenAlbumDoesNotExist(): void
    {
        $albumRepository = new InMemoryAlbumRepository();
        $heroRepository = new InMemoryHeroRepository();
        $eventBus = new InMemoryEventBus();

        $useCase = new CreateHeroUseCase($heroRepository, $albumRepository, $eventBus);

        $this->expectException(InvalidArgumentException::class);

        $useCase->execute(new CreateHeroRequest('missing', 'Thor', 'God of Thunder', 'https://example.com/thor.jpg'));
    }
}
