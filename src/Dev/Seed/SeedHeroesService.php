<?php

declare(strict_types=1);

namespace App\Dev\Seed;

use App\Albums\Domain\Repository\AlbumRepository;
use App\Heroes\Application\DTO\CreateHeroRequest;
use App\Heroes\Application\UseCase\CreateHeroUseCase;
use App\Heroes\Domain\Repository\HeroRepository;
use App\Shared\Util\Slugger;

class SeedHeroesService
{
    private const HEROES_DATA = [
        'Avengers' => [
            ['Iron Man', 'https://via.placeholder.com/800x500?text=Iron+Man', 'Armadura de alta tecnología, liderazgo y humor.'],
            ['Captain America', 'https://via.placeholder.com/800x500?text=Captain+America', 'Súper soldado con escudo indestructible.'],
            ['Thor', 'https://via.placeholder.com/800x500?text=Thor', 'Dios del trueno con Mjölnir.'],
            ['Black Widow', 'https://via.placeholder.com/800x500?text=Black+Widow', 'Espía experta y estratega.'],
            ['Hulk', 'https://via.placeholder.com/800x500?text=Hulk', 'Fuerza descomunal y gran corazón.'],
        ],
        'X-Men' => [
            ['Wolverine', 'https://via.placeholder.com/800x500?text=Wolverine', 'Garras de adamantium y regeneración.'],
            ['Cyclops', 'https://via.placeholder.com/800x500?text=Cyclops', 'Rayo óptico y liderazgo.'],
            ['Storm', 'https://via.placeholder.com/800x500?text=Storm', 'Control del clima con elegancia.'],
            ['Jean Grey', 'https://via.placeholder.com/800x500?text=Jean+Grey', 'Poder telepático y telequinesis.'],
            ['Beast', 'https://via.placeholder.com/800x500?text=Beast', 'Genio científico y acrobacia.'],
        ],
        'Guardians of the Galaxy' => [
            ['Star-Lord', 'https://via.placeholder.com/800x500?text=Star-Lord', 'Aventurero espacial con música.'],
            ['Gamora', 'https://via.placeholder.com/800x500?text=Gamora', 'Guerrera letal con honor.'],
            ['Drax', 'https://via.placeholder.com/800x500?text=Drax', 'Literal y poderoso.'],
            ['Rocket Raccoon', 'https://via.placeholder.com/800x500?text=Rocket', 'Ingeniero y piloto genial.'],
            ['Groot', 'https://via.placeholder.com/800x500?text=Groot', '‘Yo soy Groot’ y mucha voluntad.'],
        ],
    ];

    public function __construct(
        private readonly AlbumRepository $albumRepository,
        private readonly HeroRepository $heroRepository,
        private readonly CreateHeroUseCase $createHeroUseCase
    ) {
    }

    public function seedIfEmpty(): void
    {
        $albums = $this->albumRepository->all();

        foreach ($albums as $album) {
            if ($this->heroRepository->byAlbum($album->albumId()) !== []) {
                return;
            }
        }

        $this->seed($albums, false);
    }

    public function seedForce(): int
    {
        return $this->seed($this->albumRepository->all(), true);
    }

    /**
     * @param array<int, \App\Albums\Domain\Entity\Album> $albums
     */
    private function seed(array $albums, bool $force): int
    {
        $createdCount = 0;

        foreach ($albums as $album) {
            $albumId = $album->albumId();
            $albumName = $album->nombre();

            $heroDataForAlbum = null;
            foreach (self::HEROES_DATA as $key => $data) {
                if (strcasecmp($key, $albumName) === 0) {
                    $heroDataForAlbum = $data;
                    break;
                }
            }

            if ($heroDataForAlbum === null) {
                continue;
            }

            $existingSlugs = array_map(
                static fn ($hero): string => Slugger::slugify($hero->nombre()),
                $this->heroRepository->byAlbum($albumId)
            );

            if (!$force && $existingSlugs !== []) {
                continue;
            }

            foreach ($heroDataForAlbum as $heroData) {
                [$name, $image, $content] = $heroData;
                $slug = Slugger::slugify($name);

                if ($force && in_array($slug, $existingSlugs, true)) {
                    continue;
                }

                $this->createHeroUseCase->execute(
                    new CreateHeroRequest($albumId, $name, $content, $image)
                );
                $existingSlugs[] = $slug;
                $createdCount++;
            }
        }

        return $createdCount;
    }
}
