<?php

declare(strict_types=1);

namespace App\Heroes\Application\Rag;

use App\Heroes\Domain\Entity\Hero;

interface HeroRagSyncer
{
    public function sync(Hero $hero): void;
}
