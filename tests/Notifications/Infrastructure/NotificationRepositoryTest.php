<?php

declare(strict_types=1);

namespace Tests\Notifications\Infrastructure;

use App\Notifications\Infrastructure\NotificationRepository;
use PHPUnit\Framework\TestCase;

final class NotificationRepositoryTest extends TestCase
{
    private string $filePath;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filePath = __DIR__ . '/../../tmp/notification_repository.log';
        $this->tempDir = sys_get_temp_dir() . '/notification-repo-dir-' . uniqid('', true);
        if (is_file($this->filePath)) {
            unlink($this->filePath);
        }
    }

    public function testItReturnsEmptyArrayWhenFileMissing(): void
    {
        @unlink($this->filePath);

        $repository = new NotificationRepository($this->filePath);
        $notifications = $repository->lastNotifications();

        self::assertSame([], $notifications);
    }

    public function testItKeepsOnlyLastTwentyEntries(): void
    {
        $lines = [];
        for ($i = 1; $i <= 25; $i++) {
            $lines[] = sprintf('[2024-01-01T%02d:00:00Z] Message %d', $i, $i);
        }

        file_put_contents($this->filePath, implode(PHP_EOL, $lines));

        $repository = new NotificationRepository($this->filePath);
        $notifications = $repository->lastNotifications();

        self::assertCount(20, $notifications);
        self::assertSame('Message 25', $notifications[0]['message']);
        self::assertSame('Message 6', $notifications[19]['message']);
    }

    public function testItTreatsDirectoryPathAsFileWithinDirectoryWithoutWarnings(): void
    {
        mkdir($this->tempDir, 0777, true);

        $warnings = [];
        set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
            $warnings[] = $message;
            return true;
        });

        try {
            $repository = new NotificationRepository($this->tempDir);
            $repository->clear();
            $notifications = $repository->lastNotifications();
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $warnings, 'No debe emitir notices/warnings al usar un directorio como path.');
        self::assertSame([], $notifications);
        self::assertFileExists($this->tempDir . '/notifications.log');
    }

    protected function tearDown(): void
    {
        if (is_file($this->filePath)) {
            unlink($this->filePath);
        }

        if (is_dir($this->tempDir)) {
            $items = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->tempDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($items as $item) {
                $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
            }
            @rmdir($this->tempDir);
        }

        parent::tearDown();
    }
}
