<?php

declare(strict_types=1);

namespace App\Heroes\Application\UseCase;

use App\Albums\Domain\Repository\AlbumRepository;
use App\Heroes\Application\DTO\CreateHeroRequest;
use App\Heroes\Application\DTO\HeroResponse;
use App\Heroes\Domain\Entity\Hero;
use App\Heroes\Domain\Event\HeroCreated;
use App\Heroes\Domain\Repository\HeroRepository;
use App\Shared\Domain\Bus\EventBus;
use InvalidArgumentException;

final class CreateHeroUseCase
{
    public function __construct(
        private readonly HeroRepository $heroRepository,
        private readonly AlbumRepository $albumRepository,
        private readonly EventBus $eventBus
    ) {
    }

    public function execute(CreateHeroRequest $request): HeroResponse
    {
        $album = $this->albumRepository->find($request->albumId());

        if ($album === null) {
            throw new InvalidArgumentException('El álbum indicado no existe.');
        }

        $hero = Hero::create(
            self::generateUuid(),
            $album->albumId(),
            $request->nombre(),
            $request->contenido(),
            $request->imagen()
        );

        $this->heroRepository->save($hero);

        $event = HeroCreated::forHero($hero, $album->nombre());
        $this->eventBus->publish([$event]);

        return HeroResponse::fromHero($hero);
    }

    private static function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        $segments = [
            bin2hex(substr($data, 0, 4)),
            bin2hex(substr($data, 4, 2)),
            bin2hex(substr($data, 6, 2)),
            bin2hex(substr($data, 8, 2)),
            bin2hex(substr($data, 10, 6)),
        ];

        return implode('-', $segments);
    }
}
