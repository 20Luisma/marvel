<?php

declare(strict_types=1);

namespace App\Shared\Domain\Bus;

use DateTimeImmutable;

abstract class DomainEvent
{
    private string $eventId;
    protected string $aggregateId;
    private DateTimeImmutable $occurredOn;

    public function __construct(
        string $aggregateId,
        ?string $eventId = null,
        ?DateTimeImmutable $occurredOn = null
    ) {
        $this->aggregateId = $aggregateId;
        $this->eventId = $eventId ?? bin2hex(random_bytes(8));
        $this->occurredOn = $occurredOn ?? new DateTimeImmutable();
    }

    public function eventId(): string
    {
        return $this->eventId;
    }

    public function aggregateId(): string
    {
        return $this->aggregateId;
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    abstract public static function eventName(): string;

    /**
     * @return array<string, mixed>
     */
    abstract public function toPrimitives(): array;

    /**
     * @param array<string, mixed> $body
     */
    public static function fromPrimitives(
        string $aggregateId,
        array $body,
        ?string $eventId,
        ?DateTimeImmutable $occurredOn
    ): static {
        throw new \LogicException('fromPrimitives() must be implemented in the child class.');
    }
}
