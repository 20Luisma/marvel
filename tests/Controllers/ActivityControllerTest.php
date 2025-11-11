<?php

declare(strict_types=1);

namespace Tests\Controllers;

use App\Activities\Application\DTO\RecordActivityRequest;
use App\Activities\Application\UseCase\ClearActivityLogUseCase;
use App\Activities\Application\UseCase\ListActivityLogUseCase;
use App\Activities\Application\UseCase\RecordActivityUseCase;
use App\Activities\Domain\ActivityEntry;
use App\Activities\Domain\ActivityLogRepository;
use App\Activities\Domain\ActivityScope;
use PHPUnit\Framework\TestCase;
use Src\Controllers\ActivityController;

final class ActivityControllerTest extends TestCase
{
    private ActivityController $controller;
    private ActivityRepositoryDouble $repository;

    protected function setUp(): void
    {
        $this->repository = new ActivityRepositoryDouble();
        $this->controller = new ActivityController(
            new ListActivityLogUseCase($this->repository),
            new RecordActivityUseCase($this->repository),
            new ClearActivityLogUseCase($this->repository)
        );

        http_response_code(200);
    }

    public function testStoreCreatesActivityAndReturns201(): void
    {
        $this->setJsonBody(['action' => 'created', 'title' => 'Nuevo album']);

        $payload = $this->captureJson(fn () => $this->controller->store(ActivityScope::ALBUMS));

        self::assertSame('éxito', $payload['estado']);
        self::assertSame('created', $payload['datos']['action']);
        self::assertSame(201, http_response_code());
    }

    public function testIndexReturnsStoredEntries(): void
    {
        $request = new RecordActivityRequest(ActivityScope::ALBUMS, null, 'created', 'Album A');
        (new RecordActivityUseCase($this->repository))->execute($request);

        $payload = $this->captureJson(fn () => $this->controller->index(ActivityScope::ALBUMS));

        self::assertSame('éxito', $payload['estado']);
        self::assertCount(1, $payload['datos']);
    }

    public function testClearReturnsValidationErrorForInvalidScope(): void
    {
        $payload = $this->captureJson(fn () => $this->controller->clear('invalid'));

        self::assertSame('error', $payload['estado']);
        self::assertSame(400, http_response_code());
    }

    private function setJsonBody(array $body): void
    {
        $GLOBALS['mock_php_input'] = json_encode($body, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    private function captureJson(callable $callable): array
    {
        ob_start();
        $callable();
        $contents = (string) ob_get_clean();

        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }
}

final class ActivityRepositoryDouble implements ActivityLogRepository
{
    /**
     * @var array<string, list<ActivityEntry>>
     */
    private array $entries = [];

    public function all(string $scope, ?string $contextId = null): array
    {
        return $this->entries[$this->key($scope, $contextId)] ?? [];
    }

    public function append(ActivityEntry $entry): void
    {
        $key = $this->key($entry->scope(), $entry->contextId());
        $this->entries[$key] ??= [];
        $this->entries[$key][] = $entry;
    }

    public function clear(string $scope, ?string $contextId = null): void
    {
        if (!array_key_exists($this->key($scope, $contextId), $this->entries)) {
            throw new \InvalidArgumentException('Scope de actividad no soportado: ' . $scope);
        }

        unset($this->entries[$this->key($scope, $contextId)]);
    }

    private function key(string $scope, ?string $contextId): string
    {
        return $scope . ':' . ($contextId ?? '-');
    }
}
