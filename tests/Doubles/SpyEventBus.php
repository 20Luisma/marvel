<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Shared\Domain\Bus\EventBus;
use App\Shared\Domain\Event\DomainEventHandler;

final class SpyEventBus implements EventBus
{
    public int $publishCalls = 0;

    public function subscribe(DomainEventHandler $handler): void
    {
    }

    public function publish(array $events): void
    {
        $this->publishCalls++;
    }
}

