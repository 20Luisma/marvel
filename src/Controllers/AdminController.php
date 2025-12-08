<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Dev\Seed\SeedHeroesService;
use App\Shared\Http\JsonResponse;

final class AdminController
{
    public function __construct(private readonly SeedHeroesService $seedHeroesService)
    {
    }

    public function seedAll(): void
    {
        if (($_GET['key'] ?? null) !== 'dev') {
            JsonResponse::error('Unauthorized', 403);
            return;
        }

        $createdCount = $this->seedHeroesService->seedForce();
        JsonResponse::success(['created' => $createdCount], 201);
    }
}
