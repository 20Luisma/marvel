<?php

declare(strict_types=1);

namespace App\Heroes\Application\UseCase;

use App\Heroes\Application\DTO\HeroResponse;
use App\Heroes\Application\DTO\UpdateHeroRequest;
use App\Heroes\Application\Rag\HeroRagSyncer;
use App\Heroes\Domain\Repository\HeroRepository;
use InvalidArgumentException;
use Throwable;

final class UpdateHeroUseCase
{
    public function __construct(
        private readonly HeroRepository $heroRepository,
        private readonly ?HeroRagSyncer $ragSyncer = null
    )
    {
    }

    public function execute(UpdateHeroRequest $request): HeroResponse
    {
        $hero = $this->heroRepository->find($request->heroId());

        if ($hero === null) {
            throw new InvalidArgumentException('El héroe indicado no existe.');
        }

        if ($request->nombre() !== null) {
            $hero->rename($request->nombre());
        }

        if ($request->contenido() !== null) {
            $hero->updateContent($request->contenido());
        }

        if ($request->imagen() !== null) {
            $hero->changeImage($request->imagen());
        }

        $this->heroRepository->save($hero);

        if ($this->ragSyncer !== null) {
            try {
                $this->ragSyncer->sync($hero);
            } catch (Throwable $exception) {
                error_log('RAG sync (update) failed: ' . $exception->getMessage());
            }
        }

        return HeroResponse::fromHero($hero);
    }
}
