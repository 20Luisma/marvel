<?php

declare(strict_types=1);

namespace Tests\Notifications\Application;

use App\Notifications\Application\ListNotificationsUseCase;
use App\Notifications\Infrastructure\NotificationRepository;
use PHPUnit\Framework\TestCase;

final class ListNotificationsUseCaseTest extends TestCase
{
    private string $filePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filePath = __DIR__ . '/../../tmp/list_notifications.log';
        if (is_file($this->filePath)) {
            unlink($this->filePath);
        }
        file_put_contents(
            $this->filePath,
            "[2024-01-01T10:00:00Z] First message\nPlain line without metadata\n[2024-01-02T11:00:00Z] Second message\n"
        );
    }

    public function testItReturnsLatestNotificationsInReverseChronologicalOrder(): void
    {
        $repository = new NotificationRepository($this->filePath);
        $useCase = new ListNotificationsUseCase($repository);

        $notifications = $useCase->execute();

        self::assertCount(3, $notifications);
        self::assertSame('2024-01-02T11:00:00Z', $notifications[0]['date']);
        self::assertSame('Second message', $notifications[0]['message']);
        self::assertSame('', $notifications[1]['date']);
        self::assertSame('Plain line without metadata', $notifications[1]['message']);
        self::assertSame('2024-01-01T10:00:00Z', $notifications[2]['date']);
    }

    protected function tearDown(): void
    {
        if (is_file($this->filePath)) {
            unlink($this->filePath);
        }

        parent::tearDown();
    }
}
