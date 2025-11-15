<?php

declare(strict_types=1);

namespace Tests\Activities\Domain;

use App\Activities\Domain\ActivityEntry;
use App\Activities\Domain\ActivityScope;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ActivityEntryTest extends TestCase
{
    public function testCreateNormalizesValuesAndProducesTimestamp(): void
    {
        $entry = ActivityEntry::create(' HEROES ', ' hero-123 ', '  Created  ', '  Nuevo héroe  ');

        self::assertSame(ActivityScope::HEROES, $entry->scope());
        self::assertSame('hero-123', $entry->contextId());
        self::assertSame('Created', $entry->action());
        self::assertSame('Nuevo héroe', $entry->title());

        $payload = $entry->toPrimitives();
        self::assertArrayHasKey('timestamp', $payload);
        self::assertMatchesRegularExpression('/^\\d{4}-\\d{2}-\\d{2}T/', $payload['timestamp']);
    }

    public function testCreateThrowsExceptionWhenTitleIsEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El título de actividad no puede estar vacío.');

        ActivityEntry::create(ActivityScope::ALBUMS, null, 'Action', '   ');
    }

    public function testFromPrimitivesHandlesInvalidTimestamp(): void
    {
        $entry = ActivityEntry::fromPrimitives([
            'scope' => ActivityScope::ALBUMS,
            'contextId' => null,
            'action' => '  Updated  ',
            'title' => '  Album listo ',
            'timestamp' => 'not-a-date',
        ]);

        $payload = $entry->toPrimitives();

        self::assertSame(ActivityScope::ALBUMS, $payload['scope']);
        self::assertSame('Updated', $payload['action']);
        self::assertSame('Album listo', $payload['title']);
        self::assertNotSame('not-a-date', $payload['timestamp']);
        self::assertNotSame('', $payload['timestamp']);
    }
}
