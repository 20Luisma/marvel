<?php

declare(strict_types=1);

namespace App\Notifications\Application;

use App\Notifications\Infrastructure\NotificationRepository;

final class ClearNotificationsUseCase
{
    public function __construct(private readonly NotificationRepository $repository)
    {
    }

    public function execute(): void
    {
        $this->repository->clear();
    }
}
