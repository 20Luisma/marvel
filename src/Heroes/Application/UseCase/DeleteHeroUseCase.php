<?php

declare(strict_types=1);

namespace App\Heroes\Application\UseCase;

use App\Heroes\Domain\Repository\HeroRepository;
use InvalidArgumentException;

final class DeleteHeroUseCase
{
    public function __construct(private readonly HeroRepository $heroRepository)
    {
    }

    public function execute(string $heroId): void
    {
        $hero = $this->heroRepository->find($heroId);

        if ($hero === null) {
            throw new InvalidArgumentException('Héroe no encontrado.');
        }

        $this->heroRepository->delete($heroId);
    }
}
