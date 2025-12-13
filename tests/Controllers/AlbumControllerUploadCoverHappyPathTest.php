<?php

declare(strict_types=1);

namespace App\Controllers;

function is_uploaded_file(string $filename): bool
{
    if (array_key_exists('__album_test_is_uploaded_file', $GLOBALS)) {
        return (bool) $GLOBALS['__album_test_is_uploaded_file'];
    }

    return \is_uploaded_file($filename);
}

function move_uploaded_file(string $from, string $to): bool
{
    if (array_key_exists('__album_test_move_uploaded_file', $GLOBALS)) {
        if ($GLOBALS['__album_test_move_uploaded_file'] === false) {
            return false;
        }

        return @copy($from, $to);
    }

    return \move_uploaded_file($from, $to);
}

function finfo_open(int $options = 0): mixed
{
    if (array_key_exists('__album_test_finfo_handle', $GLOBALS)) {
        return $GLOBALS['__album_test_finfo_handle'];
    }

    return \finfo_open($options);
}

function finfo_file(mixed $finfo, string $filename): string|false
{
    if (array_key_exists('__album_test_mime', $GLOBALS)) {
        return (string) $GLOBALS['__album_test_mime'];
    }

    return \finfo_file($finfo, $filename);
}

function finfo_close(mixed $finfo): bool
{
    if (array_key_exists('__album_test_finfo_handle', $GLOBALS)) {
        return true;
    }

    return \finfo_close($finfo);
}

function random_bytes(int $length): string
{
    if (($GLOBALS['__album_test_random_bytes_throw'] ?? false) === true) {
        throw new \RuntimeException('random_bytes failed');
    }

    if (array_key_exists('__album_test_random_bytes', $GLOBALS)) {
        $value = (string) $GLOBALS['__album_test_random_bytes'];
        return strlen($value) === $length ? $value : substr($value . str_repeat('a', $length), 0, $length);
    }

    return \random_bytes($length);
}

namespace Tests\Controllers;

use App\Albums\Application\UseCase\CreateAlbumUseCase;
use App\Albums\Application\UseCase\DeleteAlbumUseCase;
use App\Albums\Application\UseCase\FindAlbumUseCase;
use App\Albums\Application\UseCase\ListAlbumsUseCase;
use App\Albums\Application\UseCase\UpdateAlbumUseCase;
use App\Albums\Domain\Entity\Album;
use App\Controllers\AlbumController;
use App\Heroes\Domain\Repository\HeroRepository;
use App\Shared\Infrastructure\Bus\InMemoryEventBus;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemoryAlbumRepository;

/**
 * @runTestsInSeparateProcesses
 */
final class AlbumControllerUploadCoverHappyPathTest extends TestCase
{
    private string $uploadDir;
    private AlbumController $controller;
    private InMemoryAlbumRepository $albums;

    protected function setUp(): void
    {
        parent::setUp();
        $this->uploadDir = sys_get_temp_dir() . '/clean-marvel-upload-' . uniqid('', true);

        if (!defined('ALBUM_COVER_MAX_BYTES')) {
            define('ALBUM_COVER_MAX_BYTES', 5242880);
        }
        if (!defined('ALBUM_UPLOAD_DIR')) {
            define('ALBUM_UPLOAD_DIR', $this->uploadDir);
        }
        if (!defined('ALBUM_UPLOAD_URL_PREFIX')) {
            define('ALBUM_UPLOAD_URL_PREFIX', '/uploads/albums/');
        }

        $this->albums = new InMemoryAlbumRepository();
        $eventBus = new InMemoryEventBus();

        $heroRepo = new class implements HeroRepository {
            public function save(\App\Heroes\Domain\Entity\Hero $hero): void {}
            public function find(string $heroId): ?\App\Heroes\Domain\Entity\Hero { return null; }
            public function delete(string $heroId): void {}
            public function all(): array { return []; }
            public function byAlbum(string $albumId): array { return []; }
            public function deleteByAlbum(string $albumId): void {}
        };

        $this->controller = new AlbumController(
            new ListAlbumsUseCase($this->albums),
            new CreateAlbumUseCase($this->albums),
            new UpdateAlbumUseCase($this->albums, $eventBus),
            new DeleteAlbumUseCase($this->albums, $heroRepo),
            new FindAlbumUseCase($this->albums),
        );

        $_FILES = [];
        $GLOBALS['__album_test_is_uploaded_file'] = true;
        $GLOBALS['__album_test_move_uploaded_file'] = true;
        $GLOBALS['__album_test_finfo_handle'] = 'finfo';
        $GLOBALS['__album_test_mime'] = 'image/jpeg';
        $GLOBALS['__album_test_random_bytes'] = str_repeat('a', 6);
        unset($GLOBALS['__album_test_random_bytes_throw']);
    }

    protected function tearDown(): void
    {
        $_FILES = [];
        unset(
            $GLOBALS['__album_test_is_uploaded_file'],
            $GLOBALS['__album_test_move_uploaded_file'],
            $GLOBALS['__album_test_finfo_handle'],
            $GLOBALS['__album_test_mime'],
            $GLOBALS['__album_test_random_bytes'],
            $GLOBALS['__album_test_random_bytes_throw'],
        );
        $this->removeDir($this->uploadDir);
        parent::tearDown();
    }

    public function testUploadCoverHappyPathUpdatesAlbumAndWritesFile(): void
    {
        $albumId = 'album-123';
        $this->albums->save(Album::create($albumId, 'Álbum Test', null));

        $tmpFile = tempnam(sys_get_temp_dir(), 'cover-');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, 'fake-image-bytes');

        $_FILES['file'] = [
            'name' => 'cover.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpFile),
        ];

        $payload = $this->capturePayload(function () use ($albumId): void {
            $this->controller->uploadCover($albumId);
        });

        self::assertSame('éxito', $payload['estado'] ?? null);
        self::assertSame($albumId, $payload['datos']['albumId'] ?? null);

        $expectedFilename = $albumId . '-' . bin2hex(str_repeat('a', 6)) . '.jpg';
        $expectedUrl = '/uploads/albums/' . $expectedFilename;
        self::assertSame($expectedUrl, $payload['datos']['coverImage'] ?? null);

        self::assertFileExists($this->uploadDir . DIRECTORY_SEPARATOR . $expectedFilename);
        self::assertSame($expectedUrl, $this->albums->find($albumId)?->coverImage());
    }

    public function testUploadCoverRejectsInvalidMimeType(): void
    {
        $albumId = 'album-124';
        $this->albums->save(Album::create($albumId, 'Álbum Test', null));

        $GLOBALS['__album_test_mime'] = 'text/plain';

        $tmpFile = tempnam(sys_get_temp_dir(), 'cover-');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, 'not-an-image');

        $_FILES['file'] = [
            'name' => 'cover.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpFile),
        ];

        $payload = $this->capturePayload(function () use ($albumId): void {
            $this->controller->uploadCover($albumId);
        });

        self::assertSame('error', $payload['estado'] ?? null);
        self::assertStringContainsString('no es una imagen', (string) ($payload['message'] ?? ''));
    }

    public function testUploadCoverFailsWhenMoveUploadedFileFails(): void
    {
        $albumId = 'album-125';
        $this->albums->save(Album::create($albumId, 'Álbum Test', null));

        $GLOBALS['__album_test_move_uploaded_file'] = false;

        $tmpFile = tempnam(sys_get_temp_dir(), 'cover-');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, 'fake-image-bytes');

        $_FILES['file'] = [
            'name' => 'cover.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpFile),
        ];

        $payload = $this->capturePayload(function () use ($albumId): void {
            $this->controller->uploadCover($albumId);
        });

        self::assertSame('error', $payload['estado'] ?? null);
        self::assertSame('No se pudo guardar el archivo.', $payload['message'] ?? null);
    }

    public function testUploadCoverReturnsControlled500WhenRandomBytesFails(): void
    {
        $albumId = 'album-126';
        $this->albums->save(Album::create($albumId, 'Álbum Test', null));

        $GLOBALS['__album_test_random_bytes_throw'] = true;

        $tmpFile = tempnam(sys_get_temp_dir(), 'cover-');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, 'fake-image-bytes');

        $_FILES['file'] = [
            'name' => 'cover.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpFile),
        ];

        $payload = $this->capturePayload(function () use ($albumId): void {
            $this->controller->uploadCover($albumId);
        });

        self::assertSame('error', $payload['estado'] ?? null);
        self::assertSame('No se pudo generar el nombre del archivo.', $payload['message'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private function capturePayload(callable $callable): array
    {
        ob_start();
        $callable();
        ob_get_clean();

        return \App\Shared\Http\JsonResponse::lastPayload() ?? [];
    }

    private function removeDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($path);
    }
}
