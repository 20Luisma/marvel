<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Domain\Bus\DomainEvent;
use App\Shared\Domain\Bus\EventBus;
use App\Shared\Domain\Event\DomainEventHandler;

final class InMemoryEventBus implements EventBus
{
    /**
     * @var array<string, array<int, DomainEventHandler>>
     */
    private array $handlers = [];

    public function subscribe(DomainEventHandler $handler): void
    {
        $eventName = $handler::subscribedTo();
        $this->handlers[$eventName][] = $handler;
    }

    /**
     * @param array<int, DomainEvent> $events
     */
    public function publish(array $events): void
    {
        foreach ($events as $event) {
            $eventName = $event::eventName();
            $handlers = $this->handlers[$eventName] ?? [];

            foreach ($handlers as $handler) {
                $handler($event);
            }
        }
    }
}
