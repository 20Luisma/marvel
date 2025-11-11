<?php

declare(strict_types=1);

namespace App\Notifications\Application;

use App\Heroes\Domain\Event\HeroCreated;
use App\Notifications\Domain\Service\NotificationSender;
use App\Shared\Domain\Bus\DomainEvent;
use App\Shared\Domain\Event\DomainEventHandler;

final class HeroCreatedNotificationHandler implements DomainEventHandler
{
    public function __construct(private readonly NotificationSender $sender)
    {
    }

    public static function subscribedTo(): string
    {
        return HeroCreated::eventName();
    }

    public function __invoke(DomainEvent $event): void
    {
        if (!$event instanceof HeroCreated) {
            return;
        }

        $message = sprintf('Nuevo héroe creado: %s (álbum: %s)', $event->name(), $event->albumName());
        $this->sender->send($message);
    }
}
