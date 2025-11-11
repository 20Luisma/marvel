<?php

declare(strict_types=1);

namespace Tests\Activities;

use App\Activities\Application\DTO\RecordActivityRequest;
use App\Activities\Application\UseCase\ClearActivityLogUseCase;
use App\Activities\Application\UseCase\ListActivityLogUseCase;
use App\Activities\Application\UseCase\RecordActivityUseCase;
use App\Activities\Domain\ActivityEntry;
use App\Activities\Domain\ActivityLogRepository;
use App\Activities\Domain\ActivityScope;
use PHPUnit\Framework\TestCase;

final class ActivityModuleTest extends TestCase
{
    private InMemoryActivityLogRepository $repository;
    private RecordActivityUseCase $record;
    private ListActivityLogUseCase $list;
    private ClearActivityLogUseCase $clear;

    protected function setUp(): void
    {
        $this->repository = new InMemoryActivityLogRepository();
        $this->record = new RecordActivityUseCase($this->repository);
        $this->list = new ListActivityLogUseCase($this->repository);
        $this->clear = new ClearActivityLogUseCase($this->repository);
    }

    public function testScopeValidationAndFileNameGeneration(): void
    {
        self::assertContains(ActivityScope::ALBUMS, ActivityScope::all());
        self::assertSame('heroes', ActivityScope::assertValid('  HEROES '));
        self::assertTrue(ActivityScope::requiresContext(ActivityScope::HEROES));
        self::assertFalse(ActivityScope::requiresContext(ActivityScope::COMIC));
        self::assertSame('heroes.json', ActivityScope::fileName('heroes'));
        self::assertSame('heroes-hero-42.json', ActivityScope::fileName('heroes', 'hero 42'));

        $this->expectException(\InvalidArgumentException::class);
        ActivityScope::assertValid('unknown');
    }

    public function testActivityEntryRoundtripWithSanitization(): void
    {
        $entry = ActivityEntry::create('albums', null, '  updated  ', '  Album listo  ');
        $payload = $entry->toPrimitives();

        self::assertSame('updated', $payload['action']);
        self::assertSame('Album listo', $payload['title']);
        self::assertNotEmpty($payload['timestamp']);

        $cloned = ActivityEntry::fromPrimitives($payload);
        self::assertSame($entry->scope(), $cloned->scope());
        self::assertSame($entry->action(), $cloned->action());
    }

    public function testRecordAndListUseCasesPersistEntriesAsPrimitives(): void
    {
        $request = new RecordActivityRequest(ActivityScope::ALBUMS, null, 'created', 'Nuevo álbum');
        $result = $this->record->execute($request);

        self::assertSame('created', $result['action']);
        self::assertSame('albums', $result['scope']);

        $list = $this->list->execute(ActivityScope::ALBUMS);
        self::assertCount(1, $list);
        self::assertSame('Nuevo álbum', $list[0]['title']);
    }

    public function testClearRemovesEntriesForSpecificScopeAndContext(): void
    {
        $this->record->execute(new RecordActivityRequest(ActivityScope::HEROES, 'hero-a', 'created', 'Hero A'));
        $this->record->execute(new RecordActivityRequest(ActivityScope::HEROES, 'hero-b', 'created', 'Hero B'));

        $this->clear->execute(ActivityScope::HEROES, 'hero-a');

        self::assertCount(0, $this->list->execute(ActivityScope::HEROES, 'hero-a'));
        self::assertCount(1, $this->list->execute(ActivityScope::HEROES, 'hero-b'));
    }
}

final class InMemoryActivityLogRepository implements ActivityLogRepository
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
        array_unshift($this->entries[$key], $entry);
    }

    public function clear(string $scope, ?string $contextId = null): void
    {
        unset($this->entries[$this->key($scope, $contextId)]);
    }

    private function key(string $scope, ?string $contextId): string
    {
        return $scope . ':' . ($contextId ?? '-');
    }
}
