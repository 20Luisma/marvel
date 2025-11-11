<?php

declare(strict_types=1);

namespace Tests\Notifications\Application;

use App\Albums\Domain\Entity\Album;
use App\Albums\Domain\Event\AlbumUpdated;
use App\Notifications\Application\AlbumUpdatedNotificationHandler;
use App\Notifications\Infrastructure\FileNotificationSender;
use PHPUnit\Framework\TestCase;

final class AlbumUpdatedNotificationHandlerTest extends TestCase
{
    private string $filePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filePath = __DIR__ . '/../../tmp/album_updated_handler.log';
        if (is_file($this->filePath)) {
            unlink($this->filePath);
        }
    }

    public function testItLogsCoverSetMessage(): void
    {
        $album = Album::create('album-1', 'Marvel', 'https://example.com/cover.jpg');
        $event = AlbumUpdated::fromAlbum($album);
        $handler = new AlbumUpdatedNotificationHandler(new FileNotificationSender($this->filePath));

        $handler($event);

        $lines = file($this->filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::assertNotFalse($lines);
        self::assertStringContainsString('AlbumUpdated: album-1 cover set', $lines[0]);
    }

    public function testItLogsCoverRemovedMessage(): void
    {
        $album = Album::create('album-2', 'Guardians', 'https://example.com/cover.jpg');
        $album->actualizarCover(null);
        $event = AlbumUpdated::fromAlbum($album);
        $handler = new AlbumUpdatedNotificationHandler(new FileNotificationSender($this->filePath));

        $handler($event);

        $lines = file($this->filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::assertNotFalse($lines);
        self::assertStringContainsString('AlbumUpdated: album-2 cover removed', $lines[0]);
    }

    protected function tearDown(): void
    {
        if (is_file($this->filePath)) {
            unlink($this->filePath);
        }

        parent::tearDown();
    }
}
