<?php

declare(strict_types=1);

namespace Src\Controllers;

use App\Activities\Application\DTO\RecordActivityRequest;
use App\Activities\Application\UseCase\ClearActivityLogUseCase;
use App\Activities\Application\UseCase\ListActivityLogUseCase;
use App\Activities\Application\UseCase\RecordActivityUseCase;
use App\Shared\Http\JsonResponse;
use App\Security\Sanitizer;
use InvalidArgumentException;
use Src\Controllers\Http\Request;
use Throwable;

final class ActivityController
{
    public function __construct(
        private readonly ListActivityLogUseCase $listActivity,
        private readonly RecordActivityUseCase $recordActivity,
        private readonly ClearActivityLogUseCase $clearActivity,
    ) {
    }

    public function index(string $scope, ?string $contextId = null): void
    {
        try {
            $data = $this->listActivity->execute($scope, $contextId);
            JsonResponse::success($data);
        } catch (InvalidArgumentException $exception) {
            JsonResponse::error($exception->getMessage(), 400);
        }
    }

    public function store(string $scope, ?string $contextId = null): void
    {
        $payload = Request::jsonBody();
        $sanitizer = new Sanitizer();
        $action = $sanitizer->sanitizeString((string)($payload['action'] ?? ''));
        $title = $sanitizer->sanitizeString((string)($payload['title'] ?? ''));

        try {
            $entry = $this->recordActivity->execute(
                new RecordActivityRequest($scope, $contextId, $action, $title)
            );
            JsonResponse::success($entry, 201);
        } catch (InvalidArgumentException $exception) {
            JsonResponse::error($exception->getMessage(), 422);
        } catch (Throwable $exception) {
            JsonResponse::error('No se pudo registrar la actividad: ' . $exception->getMessage(), 500);
        }
    }

    public function clear(string $scope, ?string $contextId = null): void
    {
        try {
            $this->clearActivity->execute($scope, $contextId);
            JsonResponse::success(['message' => 'Actividad eliminada.']);
        } catch (InvalidArgumentException $exception) {
            JsonResponse::error($exception->getMessage(), 400);
        }
    }
}
