<?php

declare(strict_types=1);

namespace App\Activities\Domain;

use DateTimeImmutable;

final class ActivityEntry
{
    private function __construct(
        private readonly string $scope,
        private readonly ?string $contextId,
        private readonly string $action,
        private readonly string $title,
        private readonly DateTimeImmutable $occurredAt,
    ) {
    }

    public static function create(string $scope, ?string $contextId, string $action, string $title, ?DateTimeImmutable $occurredAt = null): self
    {
        $normalizedScope = ActivityScope::assertValid($scope);
        $normalizedContext = ActivityScope::normalizeContext($normalizedScope, $contextId);
        $normalizedAction = self::sanitize($action);
        $normalizedTitle = self::sanitize($title);

        if ($normalizedAction === '') {
            throw new \InvalidArgumentException('La acción de actividad no puede estar vacía.');
        }

        if ($normalizedTitle === '') {
            throw new \InvalidArgumentException('El título de actividad no puede estar vacío.');
        }

        return new self(
            $normalizedScope,
            $normalizedContext,
            $normalizedAction,
            $normalizedTitle,
            $occurredAt ?? new DateTimeImmutable()
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromPrimitives(array $data): self
    {
        $scope = ActivityScope::assertValid((string) ($data['scope'] ?? ''));
        $contextId = ActivityScope::normalizeContext($scope, $data['contextId'] ?? null);
        $action = self::sanitize((string) ($data['action'] ?? ''));
        $title = self::sanitize((string) ($data['title'] ?? ''));

        $timestamp = isset($data['timestamp']) ? (string) $data['timestamp'] : '';
        $occurredAt = self::parseDate($timestamp) ?? new DateTimeImmutable();

        return new self($scope, $contextId, $action, $title, $occurredAt);
    }

    public function scope(): string
    {
        return $this->scope;
    }

    public function contextId(): ?string
    {
        return $this->contextId;
    }

    public function action(): string
    {
        return $this->action;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /**
     * @return array{scope: string, contextId: ?string, action: string, title: string, timestamp: string}
     */
    public function toPrimitives(): array
    {
        return [
            'scope' => $this->scope,
            'contextId' => $this->contextId,
            'action' => $this->action,
            'title' => $this->title,
            'timestamp' => $this->occurredAt->format(DateTimeImmutable::ATOM),
        ];
    }

    private static function sanitize(string $value): string
    {
        $value = trim($value);

        return mb_substr($value, 0, 255);
    }

    private static function parseDate(string $value): ?DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
