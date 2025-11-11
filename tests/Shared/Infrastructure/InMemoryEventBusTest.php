<?php

declare(strict_types=1);

namespace Tests\Shared\Infrastructure;

use App\Shared\Domain\Bus\DomainEvent;
use App\Shared\Domain\Event\DomainEventHandler;
use App\Shared\Infrastructure\Bus\InMemoryEventBus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class InMemoryEventBusTest extends TestCase
{
    public function testItDispatchesEventsToSubscribedHandlers(): void
    {
        $eventBus = new InMemoryEventBus();
        $handler = new class implements DomainEventHandler {
            public int $invocations = 0;

            public static function subscribedTo(): string
            {
                return TestDomainEvent::eventName();
            }

            public function __invoke(DomainEvent $event): void
            {
                if ($event instanceof TestDomainEvent) {
                    $this->invocations++;
                }
            }
        };

        $eventBus->subscribe($handler);

        $eventBus->publish([TestDomainEvent::occur('aggregate-1')]);

        self::assertSame(1, $handler->invocations);
    }

    public function testItIgnoresHandlersForDifferentEvents(): void
    {
        $eventBus = new InMemoryEventBus();
        $handler = new class implements DomainEventHandler {
            public int $invocations = 0;

            public static function subscribedTo(): string
            {
                return 'other.event';
            }

            public function __invoke(DomainEvent $event): void
            {
                $this->invocations++;
            }
        };

        $eventBus->subscribe($handler);
        $eventBus->publish([TestDomainEvent::occur('aggregate-1')]);

        self::assertSame(0, $handler->invocations);
    }
}

/**
 * @internal
 */
final class TestDomainEvent extends DomainEvent
{
    public static function eventName(): string
    {
        return 'test.domain.event';
    }

    /**
     * @return array<string, mixed>
     */
    public function toPrimitives(): array
    {
        return [
            'aggregateId' => $this->aggregateId(),
            'occurredOn' => $this->occurredOn()->format(DATE_ATOM),
            'eventId' => $this->eventId(),
        ];
    }

    public static function occur(string $aggregateId): self
    {
        return new self($aggregateId, null, new DateTimeImmutable());
    }
}
