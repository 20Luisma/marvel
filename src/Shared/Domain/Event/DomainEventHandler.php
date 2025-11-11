<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

use App\Shared\Domain\Bus\DomainEvent;

interface DomainEventHandler
{
    public static function subscribedTo(): string;

    public function __invoke(DomainEvent $event): void;
}
