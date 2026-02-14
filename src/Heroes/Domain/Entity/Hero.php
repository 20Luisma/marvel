<?php

declare(strict_types=1);

namespace App\Heroes\Domain\Entity;

use App\Heroes\Domain\ValueObject\HeroId;
use App\Albums\Domain\ValueObject\AlbumId;
use App\Shared\Util\Slugger;
use DateTimeImmutable;
use InvalidArgumentException;
use Throwable;

final class Hero
{
    private function __construct(
        private readonly HeroId $heroId,
        private readonly AlbumId $albumId,
        private string $name,
        private string $slug,
        private string $content,
        private string $image,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt
    ) {
        $this->assertName($name);
        $this->assertImage($image);
    }

    public static function create(
        string $heroId,
        string $albumId,
        string $nombre,
        string $contenido,
        string $imagen
    ): self {
        $now = new DateTimeImmutable();
        $slug = Slugger::slugify($nombre);

        return new self(
            new HeroId($heroId),
            new AlbumId($albumId),
            $nombre,
            $slug,
            $contenido,
            $imagen,
            $now,
            $now
        );
    }

    /**
     * @param array{heroId: string, albumId: string, nombre?: string, name?: string, slug: string, contenido?: string, content?: string, imagen?: string, image?: string, createdAt: string, updatedAt: string} $data
     */
    public static function fromPrimitives(array $data): self
    {
        $nombre = (string) ($data['nombre'] ?? $data['name'] ?? '');
        $contenido = (string) ($data['contenido'] ?? $data['content'] ?? '');
        $imagen = (string) ($data['imagen'] ?? $data['image'] ?? '');

        $createdAtValue = (string) ($data['createdAt'] ?? '');
        $updatedAtValue = (string) ($data['updatedAt'] ?? $createdAtValue);

        try {
            $createdAt = new DateTimeImmutable($createdAtValue);
        } catch (Throwable) {
            $createdAt = new DateTimeImmutable();
        }

        try {
            $updatedAt = new DateTimeImmutable($updatedAtValue);
        } catch (Throwable) {
            $updatedAt = $createdAt;
        }

        return new self(
            new HeroId($data['heroId']),
            new AlbumId($data['albumId']),
            $nombre,
            $data['slug'],
            $contenido,
            $imagen,
            $createdAt,
            $updatedAt
        );
    }

    /**
     * @return array{heroId: string, albumId: string, nombre: string, slug: string, contenido: string, imagen: string, createdAt: string, updatedAt: string}
     */
    public function toPrimitives(): array
    {
        return [
            'heroId' => $this->heroId->value(),
            'albumId' => $this->albumId->value(),
            'nombre' => $this->name,
            'slug' => $this->slug,
            'contenido' => $this->content,
            'imagen' => $this->image,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
        ];
    }

    public function heroId(): string
    {
        return $this->heroId->value();
    }

    public function albumId(): string
    {
        return $this->albumId->value();
    }



    public function nombre(): string
    {
        return $this->name;
    }

    public function name(): string
    {
        return $this->nombre();
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function contenido(): string
    {
        return $this->content;
    }

    public function content(): string
    {
        return $this->contenido();
    }

    public function imagen(): string
    {
        return $this->image;
    }

    public function image(): string
    {
        return $this->imagen();
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function updateContent(string $content): void
    {
        $this->content = $content;
        $this->touch();
    }

    public function rename(string $name): void
    {
        $this->assertName($name);
        $this->name = $name;
        $this->slug = Slugger::slugify($name);
        $this->touch();
    }

    public function changeImage(string $image): void
    {
        $this->assertImage($image);
        $this->image = $image;
        $this->touch();
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    private function assertName(string $name): void
    {
        if (trim($name) === '') {
            throw new InvalidArgumentException('El nombre del héroe no puede estar vacío.');
        }
    }

    private function assertImage(string $image): void
    {
        if (trim($image) === '') {
            throw new InvalidArgumentException('La imagen del héroe no puede estar vacía.');
        }
    }
}

