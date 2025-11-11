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

    protected function tearDown(): void
    {
        if (is_file($this->filePath)) {
            unlink($this->filePath);
        }

        parent::tearDown();
    }
}
