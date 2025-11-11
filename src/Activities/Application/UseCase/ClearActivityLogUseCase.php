<?php

declare(strict_types=1);

namespace App\Activities\Application\UseCase;

use App\Activities\Domain\ActivityLogRepository;
use App\Activities\Domain\ActivityScope;

final class ClearActivityLogUseCase
{
    public function __construct(private readonly ActivityLogRepository $repository)
    {
    }

    public function execute(string $scope, ?string $contextId = null): void
    {
        $normalizedScope = ActivityScope::assertValid($scope);
        $normalizedContext = ActivityScope::normalizeContext($normalizedScope, $contextId);

        $this->repository->clear($normalizedScope, $normalizedContext);
    }
}
