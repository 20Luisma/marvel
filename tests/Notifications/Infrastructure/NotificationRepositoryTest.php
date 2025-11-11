<?php

declare(strict_types=1);

namespace Tests\Notifications\Infrastructure;

use App\Notifications\Infrastructure\NotificationRepository;
use PHPUnit\Framework\TestCase;

final class NotificationRepositoryTest extends TestCase
{
    private string $filePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filePath = __DIR__ . '/../../tmp/notification_repository.log';
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

    protected function tearDown(): void
    {
        if (is_file($this->filePath)) {
            unlink($this->filePath);
        }

        parent::tearDown();
    }
}
