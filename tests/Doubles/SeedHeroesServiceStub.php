<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Dev\Seed\SeedHeroesService;

final class SeedHeroesServiceStub extends SeedHeroesService
{
    public int $seedForceCalls = 0;
    public int $createdCount = 0;

    public function __construct()
    {
        // SeedHeroesService has required dependencies, but they are not needed for this stub.
    }

    public function seedForce(): int
    {
        $this->seedForceCalls++;
        return $this->createdCount;
    }
}
