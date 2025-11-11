<?php

declare(strict_types=1);

namespace App\Albums\Domain\Event;

use App\Albums\Domain\Entity\Album;
use App\Shared\Domain\Bus\DomainEvent;
use DateTimeImmutable;

final class AlbumUpdated extends DomainEvent
{
    public function __construct(
        string $aggregateId,
        private readonly string $nombre,
        private readonly ?string $coverImage,
        ?string $eventId = null,
        ?DateTimeImmutable $occurredOn = null
    ) {
        parent::__construct($aggregateId, $eventId, $occurredOn);
    }

    public static function eventName(): string
    {
        return 'album.updated';
    }

    /**
     * @return array{albumId: string, nombre: string, coverImage: ?string, occurredOn: string, eventId: string}
     */
    public function toPrimitives(): array
    {
        return [
            'albumId' => $this->aggregateId(),
            'nombre' => $this->nombre,
            'coverImage' => $this->coverImage,
            'occurredOn' => $this->occurredOn()->format(DATE_ATOM),
            'eventId' => $this->eventId(),
        ];
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function fromPrimitives(
        string $aggregateId,
        array $body,
        ?string $eventId,
        ?DateTimeImmutable $occurredOn
    ): static {
        return new self(
            $aggregateId,
            $body['nombre'] ?? '',
            $body['coverImage'] ?? null,
            $eventId,
            $occurredOn
        );
    }

    public static function fromAlbum(Album $album): self
    {
        return new self(
            $album->albumId(),
            $album->nombre(),
            $album->coverImage(),
            null,
            null
        );
    }

    public function nombre(): string
    {
        return $this->nombre;
    }

    public function coverImage(): ?string
    {
        return $this->coverImage;
    }
}
