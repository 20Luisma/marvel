<?php

declare(strict_types=1);

namespace App\Notifications\Application;

use App\Notifications\Infrastructure\NotificationRepository;

final class ListNotificationsUseCase
{
    public function __construct(private readonly NotificationRepository $repository)
    {
    }

    /**
     * @return array<int, array{date: string, message: string}>
     */
    public function execute(): array
    {
        return $this->repository->lastNotifications();
    }
}
