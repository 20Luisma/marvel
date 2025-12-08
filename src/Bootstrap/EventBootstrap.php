<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Notifications\Application\AlbumUpdatedNotificationHandler;
use App\Notifications\Application\HeroCreatedNotificationHandler;
use App\Notifications\Infrastructure\FileNotificationSender;
use App\Notifications\Infrastructure\NotificationRepository;
use App\Shared\Infrastructure\Bus\InMemoryEventBus;

final class EventBootstrap
{
    /**
     * @return array{
     *   eventBus: InMemoryEventBus,
     *   notificationSender: FileNotificationSender,
     *   notificationRepository: NotificationRepository
     * }
     */
    public static function initialize(string $rootPath): array
    {
        $eventBus = new InMemoryEventBus();

        $notificationSender = new FileNotificationSender($rootPath . '/storage/notifications.log');
        $notificationRepository = new NotificationRepository($rootPath . '/storage/notifications.log');

        $notificationHandler = new HeroCreatedNotificationHandler($notificationSender);
        $albumUpdatedHandler = new AlbumUpdatedNotificationHandler($notificationSender);

        $eventBus->subscribe($notificationHandler);
        $eventBus->subscribe($albumUpdatedHandler);

        return [
            'eventBus' => $eventBus,
            'notificationSender' => $notificationSender,
            'notificationRepository' => $notificationRepository,
        ];
    }
}
