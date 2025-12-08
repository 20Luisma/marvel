<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Notifications\Application\ClearNotificationsUseCase;
use App\Notifications\Application\ListNotificationsUseCase;
use App\Shared\Http\JsonResponse;
use Throwable;

final class NotificationController
{
    public function __construct(
        private readonly ListNotificationsUseCase $listNotifications,
        private readonly ClearNotificationsUseCase $clearNotifications,
    ) {
    }

    public function index(): void
    {
        $data = $this->listNotifications->execute();
        JsonResponse::success($data);
    }

    public function clear(): void
    {
        try {
            $this->clearNotifications->execute();
            JsonResponse::success(['message' => 'Notificaciones limpiadas']);
        } catch (Throwable) {
            JsonResponse::error('No se pudieron limpiar las notificaciones.', 500);
        }
    }
}
