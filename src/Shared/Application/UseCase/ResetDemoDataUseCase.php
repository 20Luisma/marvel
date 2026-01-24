<?php

declare(strict_types=1);

namespace App\Shared\Application\UseCase;

use App\Albums\Domain\Entity\Album;
use App\Albums\Domain\Repository\AlbumRepository;
use App\Heroes\Domain\Entity\Hero;
use App\Heroes\Domain\Repository\HeroRepository;

/**
 * Caso de uso para restaurar los datos de demostración.
 * 
 * Elimina todos los álbumes y héroes actuales y los restaura
 * desde los archivos seed en storage.example/
 * 
 * Funciona tanto en entorno local (JSON) como en hosting (MySQL)
 * gracias a la abstracción de los repositorios.
 */
final class ResetDemoDataUseCase
{
    private const SEED_PATH = __DIR__ . '/../../../../storage.example';

    public function __construct(
        private readonly AlbumRepository $albumRepository,
        private readonly HeroRepository $heroRepository
    ) {
    }

    /**
     * @return array{albums: int, heroes: int}
     */
    public function execute(): array
    {
        // 1. Eliminar todos los héroes actuales
        $this->deleteAllHeroes();

        // 2. Eliminar todos los álbumes actuales
        $this->deleteAllAlbums();

        // 3. Restaurar álbumes desde seed
        $albumsRestored = $this->restoreAlbums();

        // 4. Restaurar héroes desde seed
        $heroesRestored = $this->restoreHeroes();

        return [
            'albums' => $albumsRestored,
            'heroes' => $heroesRestored,
        ];
    }

    private function deleteAllHeroes(): void
    {
        $albums = $this->albumRepository->all();
        
        foreach ($albums as $album) {
            $heroes = $this->heroRepository->byAlbum($album->albumId());
            foreach ($heroes as $hero) {
                $this->heroRepository->delete($hero->heroId());
            }
        }
    }

    private function deleteAllAlbums(): void
    {
        $albums = $this->albumRepository->all();
        
        foreach ($albums as $album) {
            $this->albumRepository->delete($album->albumId());
        }
    }

    private function restoreAlbums(): int
    {
        $seedFile = self::SEED_PATH . '/albums.json';
        
        if (!file_exists($seedFile)) {
            return 0;
        }

        $content = file_get_contents($seedFile);
        if ($content === false) {
            return 0;
        }

        /** @var array<int, array<string, mixed>> $albumsData */
        $albumsData = json_decode($content, true);
        
        if (!is_array($albumsData)) {
            return 0;
        }

        $count = 0;
        foreach ($albumsData as $data) {
            $album = Album::fromPrimitives([
                'albumId' => $data['albumId'] ?? '',
                'nombre' => $data['nombre'] ?? '',
                'coverImage' => $data['coverImage'] ?? null,
                'createdAt' => $data['createdAt'] ?? date('c'),
                'updatedAt' => $data['updatedAt'] ?? date('c'),
            ]);
            
            $this->albumRepository->save($album);
            $count++;
        }

        return $count;
    }

    private function restoreHeroes(): int
    {
        $seedFile = self::SEED_PATH . '/heroes.json';
        
        if (!file_exists($seedFile)) {
            return 0;
        }

        $content = file_get_contents($seedFile);
        if ($content === false) {
            return 0;
        }

        /** @var array<int, array<string, mixed>> $heroesData */
        $heroesData = json_decode($content, true);
        
        if (!is_array($heroesData)) {
            return 0;
        }

        $count = 0;
        foreach ($heroesData as $data) {
            $hero = Hero::fromPrimitives([
                'heroId' => $data['heroId'] ?? '',
                'albumId' => $data['albumId'] ?? '',
                'nombre' => $data['nombre'] ?? '',
                'slug' => $data['slug'] ?? '',
                'contenido' => $data['contenido'] ?? '',
                'imagen' => $data['imagen'] ?? null,
                'createdAt' => $data['createdAt'] ?? date('c'),
                'updatedAt' => $data['updatedAt'] ?? date('c'),
            ]);
            
            $this->heroRepository->save($hero);
            $count++;
        }

        return $count;
    }
}
