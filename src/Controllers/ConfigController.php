<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\ServiceUrlProvider;
use App\Shared\Http\JsonResponse;

final class ConfigController
{
    public function __construct(private readonly ServiceUrlProvider $serviceUrlProvider)
    {
    }

    public function services(): void
    {
        $payload = $this->serviceUrlProvider->toArrayForFrontend($_SERVER['HTTP_HOST'] ?? null);
        JsonResponse::success($payload);
    }
}
