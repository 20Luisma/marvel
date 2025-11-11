<?php

declare(strict_types=1);

namespace Tests\Notifications;

use App\Albums\Domain\Event\AlbumUpdated;
use App\Heroes\Domain\Event\HeroCreated;
use App\Notifications\Application\AlbumUpdatedNotificationHandler;
use App\Notifications\Application\ClearNotificationsUseCase;
use App\Notifications\Application\HeroCreatedNotificationHandler;
use App\Notifications\Application\ListNotificationsUseCase;
use App\Notifications\Domain\Service\NotificationSender;
use App\Notifications\Infrastructure\FileNotificationSender;
use App\Notifications\Infrastructure\NotificationRepository;
use PHPUnit\Framework\TestCase;

final class NotificationModuleTest extends TestCase
{
    private string $filePath;

    protected function setUp(): void
    {
        $this->filePath = sys_get_temp_dir() . '/notifications-' . uniqid('', true) . '.log';
    }

    protected function tearDown(): void
    {
        @unlink($this->filePath);
    }

    public function testRepositoryReturnsLastEntriesInReverseOrder(): void
    {
        file_put_contents($this->filePath, "[2024-01-01T00:00:00Z] First\n[2024-01-02T00:00:00Z] Second\n");
        $repository = new NotificationRepository($this->filePath);

        $notifications = $repository->lastNotifications();

        self::assertSame('Second', $notifications[0]['message']);
        self::assertCount(2, $notifications);
    }

    public function testFileSenderAppendsTimestampedLine(): void
    {
        $sender = new FileNotificationSender($this->filePath);
        $sender->send('Hola mundo');

        $contents = (string) file_get_contents($this->filePath);
        self::assertStringContainsString('Hola mundo', $contents);
        self::assertStringContainsString('[', $contents);
    }

    public function testUseCasesDelegateToRepository(): void
    {
        $repository = new NotificationRepository($this->filePath);
        file_put_contents($this->filePath, "[2024-01-01T00:00:00Z] Stored\n");

        $list = new ListNotificationsUseCase($repository);
        $clear = new ClearNotificationsUseCase($repository);

        self::assertCount(1, $list->execute());
        $clear->execute();
        self::assertSame('', (string) file_get_contents($this->filePath));
    }

    public function testAlbumUpdatedHandlerFormatsMessage(): void
    {
        $sender = new NotificationSenderSpy();
        $handler = new AlbumUpdatedNotificationHandler($sender);

        $event = new AlbumUpdated('album-1', 'Guardians', 'cover.png');
        $handler($event);

        self::assertSame('AlbumUpdated: album-1 cover set', $sender->lastMessage);
    }

    public function testHeroCreatedHandlerFormatsMessage(): void
    {
        $sender = new NotificationSenderSpy();
        $handler = new HeroCreatedNotificationHandler($sender);

        $event = new HeroCreated('hero-1', 'album-9', 'Marvel', 'Rocket', 'rocket', 'rocket.png');
        $handler($event);

        self::assertSame('Nuevo héroe creado: Rocket (álbum: Marvel)', $sender->lastMessage);
    }
}

final class NotificationSenderSpy implements NotificationSender
{
    public ?string $lastMessage = null;

    public function send(string $message): void
    {
        $this->lastMessage = $message;
    }
}
