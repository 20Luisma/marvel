<?php

declare(strict_types=1);

namespace App\Heroes\Application\UseCase;

use App\Albums\Domain\Repository\AlbumRepository;
use App\Heroes\Application\DTO\HeroResponse;
use App\Heroes\Domain\Entity\Hero;
use App\Heroes\Domain\Event\HeroCreated;
use App\Heroes\Domain\Repository\HeroRepository;
use App\Shared\Domain\Bus\EventBus;
use InvalidArgumentException;

final class SeedAlbumHeroesUseCase
{
    private const SAMPLE_HEROES = [
        [
            'nombre' => 'Iron Man',
            'imagen' => 'https://i.imgur.com/8hQq3.jpg',
            'contenido' => 'Armadura de alta tecnología, ingeniero brillante y fundador de los Vengadores.'
        ],
        [
            'nombre' => 'Captain America',
            'imagen' => 'https://i.imgur.com/4Abc2.jpg',
            'contenido' => 'Súper soldado con escudo indestructible. Liderazgo y valentía incomparables.'
        ],
        [
            'nombre' => 'Thor',
            'imagen' => 'https://i.imgur.com/DfS93.jpg',
            'contenido' => 'Dios del trueno, empuña a Mjölnir y protege los nueve reinos.'
        ],
        [
            'nombre' => 'Black Widow',
            'imagen' => 'https://i.imgur.com/XyZ12.jpg',
            'contenido' => 'Espía experta, combate cuerpo a cuerpo y táctica impecable.'
        ],
        [
            'nombre' => 'Hulk',
            'imagen' => 'https://i.imgur.com/72ZyQ.jpg',
            'contenido' => 'Fuerza descomunal alimentada por la rabia. Científico brillante convertido en héroe.'
        ],
    ];

    public function __construct(
        private readonly AlbumRepository $albumRepository,
        private readonly HeroRepository $heroRepository,
        private readonly EventBus $eventBus
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function execute(string $albumId): array
    {
        $album = $this->albumRepository->find($albumId);

        if ($album === null) {
            throw new InvalidArgumentException('Álbum no encontrado.');
        }

        $created = [];

        foreach (self::SAMPLE_HEROES as $sample) {
            $hero = Hero::create(
                self::generateUuid(),
                $album->albumId(),
                $sample['nombre'],
                $sample['contenido'],
                $sample['imagen']
            );

            $this->heroRepository->save($hero);
            $this->eventBus->publish([HeroCreated::forHero($hero, $album->nombre())]);

            $created[] = HeroResponse::fromHero($hero)->toArray();
        }

        return $created;
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
