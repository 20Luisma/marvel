<?php

declare(strict_types=1);

namespace Tests\Activities\Infrastructure;

use App\Activities\Domain\ActivityEntry;
use App\Activities\Domain\ActivityScope;
use App\Activities\Infrastructure\Persistence\FileActivityLogRepository;
use PHPUnit\Framework\TestCase;

final class FileActivityLogRepositoryTest extends TestCase
{
    private string $storageDir;

    protected function setUp(): void
    {
        $this->storageDir = sys_get_temp_dir() . '/activity-log-' . uniqid('', true);
        mkdir($this->storageDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->storageDir . '/*.json') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->storageDir);
    }

    public function testAppendStoresNewestEntriesFirstRespectingLimit(): void
    {
        $repository = new FileActivityLogRepository($this->storageDir, 2);

        $older = ActivityEntry::create(ActivityScope::ALBUMS, null, 'created', 'Album antiguo');
        $newer = ActivityEntry::create(ActivityScope::ALBUMS, null, 'updated', 'Album actualizado');
        $repository->append($older);
        $repository->append($newer);

        $entries = $repository->all(ActivityScope::ALBUMS);
        self::assertCount(2, $entries);
        self::assertSame('updated', $entries[0]->action());
        self::assertSame('created', $entries[1]->action());
    }

    public function testClearCreatesEmptyJsonFile(): void
    {
        $repository = new FileActivityLogRepository($this->storageDir);
        $entry = ActivityEntry::create(ActivityScope::COMIC, null, 'generated', 'Comic IA');
        $repository->append($entry);

        $repository->clear(ActivityScope::COMIC);

        $file = $this->storageDir . '/comic.json';
        self::assertFileExists($file);
        self::assertSame('[]', trim((string) file_get_contents($file)));
    }
}
