<?php

declare(strict_types=1);

namespace App\Activities\Domain;

interface ActivityLogRepository
{
    /**
     * @return list<ActivityEntry>
     */
    public function all(string $scope, ?string $contextId = null): array;

    public function append(ActivityEntry $entry): void;

    public function clear(string $scope, ?string $contextId = null): void;
}
