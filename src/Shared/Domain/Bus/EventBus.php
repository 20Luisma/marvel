<?php

declare(strict_types=1);

namespace App\Shared\Domain\Bus;

use App\Shared\Domain\Event\DomainEventHandler;

interface EventBus
{
    public function subscribe(DomainEventHandler $handler): void;

    /**
     * @param array<int, DomainEvent> $events
     */
    public function publish(array $events): void;
}
