<?php

declare(strict_types=1);

namespace Tests\Activities;

use App\Activities\Application\DTO\RecordActivityRequest;
use App\Activities\Application\UseCase\RecordActivityUseCase;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeActivityLogRepository;

final class RecordActivityUseCaseTest extends TestCase
{
    public function test_it_records_activity_entries_in_fake_repository(): void
    {
        $repository = new FakeActivityLogRepository();
        $useCase = new RecordActivityUseCase($repository);

        $request = new RecordActivityRequest(
            'heroes',
            'album-001',
            'CREATED',
            'Se agregó un héroe'
        );

        $payload = $useCase->execute($request);

        self::assertNotEmpty($payload);
        self::assertArrayHasKey('scope', $payload);
        self::assertArrayHasKey('contextId', $payload);
        self::assertArrayHasKey('action', $payload);
        self::assertArrayHasKey('title', $payload);
        self::assertArrayHasKey('timestamp', $payload);

        $entries = $repository->entries();

        self::assertCount(1, $entries);
        self::assertSame('heroes', $entries[0]->scope());
        self::assertSame('album-001', $entries[0]->contextId());
        self::assertSame('CREATED', $entries[0]->action());
        self::assertSame('Se agregó un héroe', $entries[0]->title());
    }
}
