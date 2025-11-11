<?php

declare(strict_types=1);

namespace Tests\Application;

use App\Albums\Domain\Entity\Album;
use App\Heroes\Application\DTO\CreateHeroRequest;
use App\Heroes\Application\UseCase\CreateHeroUseCase;
use App\Heroes\Domain\Event\HeroCreated;
use App\Shared\Domain\Bus\DomainEvent;
use App\Shared\Domain\Event\DomainEventHandler;
use App\Shared\Infrastructure\Bus\InMemoryEventBus;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemoryAlbumRepository;
use Tests\Doubles\InMemoryHeroRepository;

final class CreateHeroPublishesEventTest extends TestCase
{
    public function testItPublishesHeroCreatedEvent(): void
    {
        $albumRepository = new InMemoryAlbumRepository();
        $heroRepository = new InMemoryHeroRepository();
        $eventBus = new InMemoryEventBus();

        $albumRepository->seed(Album::create('album-1', 'Avengers'));

        $handler = new class implements DomainEventHandler {
            public array $events = [];

            public static function subscribedTo(): string
            {
                return HeroCreated::eventName();
            }

            public function __invoke(DomainEvent $event): void
            {
                if ($event instanceof HeroCreated) {
                    $this->events[] = $event->toPrimitives();
                }
            }
        };

        $eventBus->subscribe($handler);

        $useCase = new CreateHeroUseCase($heroRepository, $albumRepository, $eventBus);
        $useCase->execute(new CreateHeroRequest('album-1', 'Spider-Man', 'Friendly neighborhood hero', 'https://example.com/spidey.jpg'));

        self::assertCount(1, $handler->events);
        self::assertSame('Spider-Man', $handler->events[0]['name']);
    }
}
