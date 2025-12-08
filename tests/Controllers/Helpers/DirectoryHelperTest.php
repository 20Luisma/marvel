<?php

declare(strict_types=1);

namespace Tests\Controllers\Helpers;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use App\Controllers\Helpers\DirectoryHelper;

final class DirectoryHelperTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        $this->basePath = sys_get_temp_dir() . '/dir-helper-' . uniqid('', true);
        @mkdir($this->basePath, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->basePath);
    }

    public function testEnsureCreatesDirectoryWhenMissing(): void
    {
        $target = $this->basePath . '/nested/location';

        DirectoryHelper::ensure($target);

        self::assertDirectoryExists($target);
    }

    public function testEnsureThrowsWhenPathIsFile(): void
    {
        $filePath = $this->basePath . '/existing-file.txt';
        file_put_contents($filePath, 'noop');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No se pudo crear el directorio');

        DirectoryHelper::ensure($filePath);
    }

    public function testEnsureDoesNothingWhenDirectoryAlreadyExists(): void
    {
        $existingDir = $this->basePath . '/already-exists';
        mkdir($existingDir, 0777, true);
        
        // Ensure it exists before calling
        self::assertDirectoryExists($existingDir);
        
        // Should not throw and just return early
        DirectoryHelper::ensure($existingDir);
        
        // Directory should still exist
        self::assertDirectoryExists($existingDir);
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            if (is_file($path)) {
                @unlink($path);
            }
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $this->removeDirectory($path . DIRECTORY_SEPARATOR . $item);
        }

        @rmdir($path);
    }
}
