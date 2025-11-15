<?php

declare(strict_types=1);

namespace Tests\Repositories;

use App\Heroes\Infrastructure\Persistence\FileHeroRepository;
use PHPUnit\Framework\TestCase;

final class FileHeroRepositoryMissingFileTest extends TestCase
{
    public function test_all_returns_empty_array_when_json_file_is_missing(): void
    {
        $storageDir = __DIR__ . '/../tmp';
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0777, true);
        }

        $filePath = $storageDir . '/heroes_missing_' . uniqid('', true) . '.json';
        if (is_file($filePath)) {
            unlink($filePath);
        }

        $repository = new FileHeroRepository($filePath);

        $heroes = $repository->all();

        self::assertSame([], $heroes);

        if (is_file($filePath)) {
            unlink($filePath);
        }
    }
}
