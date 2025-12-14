<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Heroes\Application\Rag\HeroRagSyncer;
use App\Heroes\Domain\Entity\Hero;

final class SpyRagSyncer implements HeroRagSyncer
{
    public int $syncCalls = 0;

    public function sync(Hero $hero): void
    {
        $this->syncCalls++;
    }
}

