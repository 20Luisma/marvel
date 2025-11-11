<?php

declare(strict_types=1);

namespace App\Activities\Application\UseCase;

use App\Activities\Application\DTO\RecordActivityRequest;
use App\Activities\Domain\ActivityEntry;
use App\Activities\Domain\ActivityLogRepository;
use App\Activities\Domain\ActivityScope;

final class RecordActivityUseCase
{
    public function __construct(private readonly ActivityLogRepository $repository)
    {
    }

    /**
     * @return array{scope: string, contextId: ?string, action: string, title: string, timestamp: string}
     */
    public function execute(RecordActivityRequest $request): array
    {
        $scope = ActivityScope::assertValid($request->scope);
        $contextId = ActivityScope::normalizeContext($scope, $request->contextId);

        $entry = ActivityEntry::create($scope, $contextId, $request->action, $request->title);

        $this->repository->append($entry);

        return $entry->toPrimitives();
    }
}
