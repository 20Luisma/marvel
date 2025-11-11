<?php

declare(strict_types=1);

namespace App\Activities\Infrastructure\Persistence;

use App\Activities\Domain\ActivityEntry;
use App\Activities\Domain\ActivityLogRepository;
use App\Activities\Domain\ActivityScope;

final class FileActivityLogRepository implements ActivityLogRepository
{
    public function __construct(
        private readonly string $basePath,
        private readonly int $maxEntries = 100
    ) {
        $this->ensureDirectory($this->basePath);
    }

    /**
     * @return list<ActivityEntry>
     */
    public function all(string $scope, ?string $contextId = null): array
    {
        $filePath = $this->filePath($scope, $contextId);

        if (!is_file($filePath)) {
            return [];
        }

        $contents = file_get_contents($filePath);
        if ($contents === false || trim($contents) === '') {
            return [];
        }

        $decoded = json_decode($contents, true);
        if (!is_array($decoded)) {
            return [];
        }

        $collection = array_values(
            array_filter(
                $decoded,
                static fn ($item): bool => is_array($item)
            )
        );

        return array_map(
            static fn (array $data): ActivityEntry => ActivityEntry::fromPrimitives($data),
            $collection
        );
    }

    public function append(ActivityEntry $entry): void
    {
        $filePath = $this->filePath($entry->scope(), $entry->contextId());
        $this->ensureDirectory(dirname($filePath));

        $entries = $this->all($entry->scope(), $entry->contextId());

        array_unshift($entries, $entry);

        $entries = array_slice($entries, 0, $this->maxEntries);

        $this->persist($filePath, $entries);
    }

    public function clear(string $scope, ?string $contextId = null): void
    {
        $filePath = $this->filePath($scope, $contextId);
        $this->ensureDirectory(dirname($filePath));

        file_put_contents($filePath, "[]");
    }

    /**
     * @param list<ActivityEntry> $entries
     */
    private function persist(string $filePath, array $entries): void
    {
        $payload = array_map(
            static fn (ActivityEntry $entry): array => $entry->toPrimitives(),
            $entries
        );

        file_put_contents(
            $filePath,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    private function filePath(string $scope, ?string $contextId = null): string
    {
        $fileName = ActivityScope::fileName(ActivityScope::assertValid($scope), $contextId);

        return rtrim($this->basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;
    }

    private function ensureDirectory(string $directory): void
    {
        if ($directory === '' || $directory === '.') {
            return;
        }

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }
}
