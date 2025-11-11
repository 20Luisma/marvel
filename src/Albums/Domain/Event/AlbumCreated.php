<?php

declare(strict_types=1);

namespace App\Albums\Domain\Event;

use App\Albums\Domain\Entity\Album;
use App\Shared\Domain\Bus\DomainEvent;

final class AlbumCreated extends DomainEvent
{
    public function __construct(
        string $aggregateId,
        private readonly string $name,
        ?string $eventId = null,
        ?\DateTimeImmutable $occurredOn = null
    ) {
        parent::__construct($aggregateId, $eventId, $occurredOn);
    }

    public static function eventName(): string
    {
        return 'album.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function toPrimitives(): array
    {
        return [
            'name' => $this->name,
        ];
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function fromPrimitives(
        string $aggregateId,
        array $body,
        ?string $eventId,
        ?\DateTimeImmutable $occurredOn
    ): static {
        return new self(
            $aggregateId,
            $body['name'] ?? '',
            $eventId,
            $occurredOn
        );
    }

    public function name(): string
    {
        return $this->name;
    }

    public static function forAlbum(Album $album): self
    {
        return new self($album->albumId(), $album->nombre());
    }
}
