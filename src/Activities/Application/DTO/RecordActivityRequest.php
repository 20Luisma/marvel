<?php

declare(strict_types=1);

namespace App\Activities\Application\DTO;

final class RecordActivityRequest
{
    public function __construct(
        public readonly string $scope,
        public readonly ?string $contextId,
        public readonly string $action,
        public readonly string $title,
    ) {
    }
}
