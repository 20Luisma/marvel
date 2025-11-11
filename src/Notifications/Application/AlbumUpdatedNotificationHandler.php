<?php

declare(strict_types=1);

namespace App\Notifications\Application;

use App\Albums\Domain\Event\AlbumUpdated;
use App\Notifications\Domain\Service\NotificationSender;
use App\Shared\Domain\Bus\DomainEvent;
use App\Shared\Domain\Event\DomainEventHandler;

final class AlbumUpdatedNotificationHandler implements DomainEventHandler
{
    public function __construct(private readonly NotificationSender $sender)
    {
    }

    public static function subscribedTo(): string
    {
        return AlbumUpdated::eventName();
    }

    public function __invoke(DomainEvent $event): void
    {
        if (!$event instanceof AlbumUpdated) {
            return;
        }

        $cover = $event->coverImage();
        $status = $cover === null ? 'cover removed' : 'cover set';
        $message = sprintf('AlbumUpdated: %s %s', $event->aggregateId(), $status);

        $this->sender->send($message);
    }
}
