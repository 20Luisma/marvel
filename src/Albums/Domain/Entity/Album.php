<?php

declare(strict_types=1);

namespace App\Albums\Domain\Entity;

use DateTimeImmutable;
use InvalidArgumentException;
use Throwable;

final class Album
{
    private function __construct(
        private readonly string $albumId,
        private string $nombre,
        private ?string $coverImage,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt
    ) {
        $this->assertNombre($nombre);
    }

    public static function create(string $albumId, string $nombre, ?string $coverImage = null): self
    {
        $now = new DateTimeImmutable();

        return new self($albumId, $nombre, self::normalizeCover($coverImage), $now, $now);
    }

    /**
     * @param array{albumId: string, nombre?: string, name?: string, coverImage?: ?string, cover_image?: ?string, createdAt: string, updatedAt?: string} $data
     */
    public static function fromPrimitives(array $data): self
    {
        $nombre = (string) ($data['nombre'] ?? $data['name'] ?? '');

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
            $data['albumId'],
            $nombre,
            self::normalizeCover($data['coverImage'] ?? $data['cover_image'] ?? null),
            $createdAt,
            $updatedAt
        );
    }

    public function renombrar(string $nombre): void
    {
        $this->assertNombre($nombre);
        $this->nombre = $nombre;
        $this->touch();
    }

    public function actualizarCover(?string $coverImage): void
    {
        $this->coverImage = self::normalizeCover($coverImage);
        $this->touch();
    }

    /**
     * @return array{albumId: string, nombre: string, coverImage: ?string, createdAt: string, updatedAt: string}
     */
    public function toPrimitives(): array
    {
        return [
            'albumId' => $this->albumId,
            'nombre' => $this->nombre,
            'coverImage' => $this->coverImage,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
        ];
    }

    public function albumId(): string
    {
        return $this->albumId;
    }

    public function nombre(): string
    {
        return $this->nombre;
    }

    public function coverImage(): ?string
    {
        return $this->coverImage;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function assertNombre(string $nombre): void
    {
        if (trim($nombre) === '') {
            throw new InvalidArgumentException('El nombre del álbum no puede estar vacío.');
        }
    }

    private static function normalizeCover(?string $value): ?string
    {
        $trimmed = $value !== null ? trim($value) : null;

        return $trimmed === '' ? null : $trimmed;
    }

    private function touch(): void
    {
        $now = new DateTimeImmutable();

        if ($now <= $this->updatedAt) {
            $now = $this->updatedAt->modify('+1 microsecond');
        }

        $this->updatedAt = $now;
    }
}
