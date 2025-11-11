<?php

declare(strict_types=1);

namespace Tests\Infrastructure;

use App\Notifications\Application\ClearNotificationsUseCase;
use App\Notifications\Infrastructure\NotificationRepository;
use PHPUnit\Framework\TestCase;

final class NotificationsClearTest extends TestCase
{
    private string $filePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filePath = __DIR__ . '/../tmp/notifications_clear_test.log';
        if (is_file($this->filePath)) {
            unlink($this->filePath);
        }
        file_put_contents($this->filePath, "[2024-01-01T10:00:00Z] Evento anterior\n");
    }

    public function testItClearsNotificationLog(): void
    {
        $repository = new NotificationRepository($this->filePath);
        $useCase = new ClearNotificationsUseCase($repository);

        $useCase->execute();

        $contents = file_get_contents($this->filePath);
        self::assertTrue($contents === '' || $contents === false || trim($contents) === '');
    }

    protected function tearDown(): void
    {
        if (is_file($this->filePath)) {
            unlink($this->filePath);
        }

        parent::tearDown();
    }
}
