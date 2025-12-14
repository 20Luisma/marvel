<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Activities\Domain\ActivityEntry;
use App\Activities\Domain\ActivityLogRepository;

final class SpyActivityLogRepository implements ActivityLogRepository
{
    public int $appendCalls = 0;

    public function all(string $scope, ?string $contextId = null): array
    {
        return [];
    }

    public function append(ActivityEntry $entry): void
    {
        $this->appendCalls++;
    }

    public function clear(string $scope, ?string $contextId = null): void
    {
    }
}

