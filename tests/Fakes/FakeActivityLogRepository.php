<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Activities\Domain\ActivityEntry;
use App\Activities\Domain\ActivityLogRepository;

final class FakeActivityLogRepository implements ActivityLogRepository
{
    /**
     * @var array<int, ActivityEntry>
     */
    private array $entries = [];

    /**
     * @return list<ActivityEntry>
     */
    public function all(string $scope, ?string $contextId = null): array
    {
        return array_values(array_filter(
            $this->entries,
            static fn (ActivityEntry $entry): bool => $entry->scope() === $scope
                && ($contextId === null || $entry->contextId() === $contextId)
        ));
    }

    public function append(ActivityEntry $entry): void
    {
        $this->entries[] = $entry;
    }

    public function clear(string $scope, ?string $contextId = null): void
    {
        $this->entries = array_values(array_filter(
            $this->entries,
            static fn (ActivityEntry $entry): bool => $entry->scope() !== $scope
                || ($contextId !== null && $entry->contextId() !== $contextId)
        ));
    }

    /**
     * @return list<ActivityEntry>
     */
    public function entries(): array
    {
        return $this->entries;
    }
}
