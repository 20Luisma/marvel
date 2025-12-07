<?php

declare(strict_types=1);

namespace Tests\Notifications\Application;

use App\Heroes\Domain\Entity\Hero;
use App\Heroes\Domain\Event\HeroCreated;
use App\Notifications\Application\HeroCreatedNotificationHandler;
use App\Notifications\Infrastructure\FileNotificationSender;
use PHPUnit\Framework\TestCase;

final class HeroCreatedNotificationHandlerTest extends TestCase
{
    private string $filePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filePath = __DIR__ . '/../../tmp/notifications_test.log';
        if (is_file($this->filePath)) {
            unlink($this->filePath);
        }
    }

    public function testItWritesNotificationOnHeroCreated(): void
    {
        $sender = new FileNotificationSender($this->filePath);
        $handler = new HeroCreatedNotificationHandler($sender);

        $hero = Hero::create('hero-1', 'album-1', 'Hulk', 'Smash', 'https://example.com/hulk.jpg');
        $event = HeroCreated::forHero($hero, 'Avengers');

        $handler($event);

        $lines = file($this->filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        self::assertNotFalse($lines);
        self::assertNotEmpty($lines);
        self::assertStringContainsString('Nuevo héroe creado: Hulk (álbum: Avengers)', $lines[0]);
    }

    public function testSubscribedToReturnsHeroCreatedEventName(): void
    {
        $eventName = HeroCreatedNotificationHandler::subscribedTo();

        self::assertSame('hero.created', $eventName);
    }

    public function testItIgnoresNonHeroCreatedEvents(): void
    {
        $sender = new FileNotificationSender($this->filePath);
        $handler = new HeroCreatedNotificationHandler($sender);

        // Use AlbumCreated instead of HeroCreated
        $event = new \App\Albums\Domain\Event\AlbumCreated('album-1', 'Test Album');

        $handler($event);

        // File should be empty or contain nothing related to this event
        $contents = file_get_contents($this->filePath);
        self::assertStringNotContainsString('Test Album', $contents);
    }

    protected function tearDown(): void
    {
        if (is_file($this->filePath)) {
            unlink($this->filePath);
        }

        parent::tearDown();
    }
}
