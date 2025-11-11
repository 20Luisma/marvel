<?php

declare(strict_types=1);

namespace App\Activities\Application\UseCase;

use App\Activities\Domain\ActivityLogRepository;
use App\Activities\Domain\ActivityScope;

final class ListActivityLogUseCase
{
    public function __construct(private readonly ActivityLogRepository $repository)
    {
    }

    /**
     * @return list<array{scope: string, contextId: ?string, action: string, title: string, timestamp: string}>
     */
    public function execute(string $scope, ?string $contextId = null): array
    {
        $normalizedScope = ActivityScope::assertValid($scope);
        $normalizedContext = ActivityScope::normalizeContext($normalizedScope, $contextId);

        return array_map(
            static fn ($entry) => $entry->toPrimitives(),
            $this->repository->all($normalizedScope, $normalizedContext)
        );
    }
}
