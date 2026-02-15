<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Controllers\ActivityController;
use App\Controllers\AdminController;
use App\Controllers\AlbumController;
use App\Controllers\AuthController;
use App\Controllers\ConfigController;
use App\Controllers\ComicController;
use App\Controllers\DevController;
use App\Controllers\HealthCheckController;
use App\Controllers\HeroController;
use App\Controllers\NotificationController;
use App\Controllers\PageController;
use App\Controllers\RagProxyController;
use App\Controllers\TtsController;

final class ControllerBootstrap
{
    /**
     * @param array<string, mixed> $container
     */
    public static function initialize(array &$container): void
    {
        // Esta clase podría usarse para pre-instanciar controladores si fuera necesario,
        // pero el Router ya los instancia bajo demanda (Lazy Loading).
        // Por ahora, solo nos aseguramos de que el Router tenga lo que necesita.
    }
}
