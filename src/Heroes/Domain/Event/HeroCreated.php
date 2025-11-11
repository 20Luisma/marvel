<?php

declare(strict_types=1);

namespace App\Heroes\Domain\Event;

use App\Heroes\Domain\Entity\Hero;
use App\Shared\Domain\Bus\DomainEvent;
use DateTimeImmutable;

final class HeroCreated extends DomainEvent
{
    public function __construct(
        string $aggregateId,
        private readonly string $albumId,
        private readonly string $albumName,
        private readonly string $name,
        private readonly string $slug,
        private readonly string $image,
        ?string $eventId = null,
        ?DateTimeImmutable $occurredOn = null
    ) {
        parent::__construct($aggregateId, $eventId, $occurredOn);
    }

    public static function eventName(): string
    {
        return 'hero.created';
    }

    /**
     * @return array{heroId: string, albumId: string, albumName: string, name: string, slug: string, image: string, occurredOn: string}
     */
    public function toPrimitives(): array
    {
        return [
            'heroId' => $this->aggregateId(),
            'albumId' => $this->albumId,
            'albumName' => $this->albumName,
            'name' => $this->name,
            'slug' => $this->slug,
            'image' => $this->image,
            'occurredOn' => $this->occurredOn()->format(DATE_ATOM),
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
            $body['albumId'] ?? '',
            $body['albumName'] ?? '',
            $body['name'] ?? '',
            $body['slug'] ?? '',
            $body['image'] ?? '',
            $eventId,
            $occurredOn
        );
    }

    public static function forHero(Hero $hero, string $albumName): self
    {
        return new self(
            $hero->heroId(),
            $hero->albumId(),
            $albumName,
            $hero->name(),
            $hero->slug(),
            $hero->image(),
            null,
            null
        );
    }

    public function albumId(): string
    {
        return $this->albumId;
    }

    public function albumName(): string
    {
        return $this->albumName;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function image(): string
    {
        return $this->image;
    }
}
