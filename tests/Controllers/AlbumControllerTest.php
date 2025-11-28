<?php

declare(strict_types=1);

namespace Src\Controllers;

if (!function_exists(__NAMESPACE__ . '\is_uploaded_file')) {
    function is_uploaded_file(string $filename): bool
    {
        return in_array($filename, $GLOBALS['__album_controller_uploaded_files__'] ?? [], true);
    }
}

if (!function_exists(__NAMESPACE__ . '\move_uploaded_file')) {
    function move_uploaded_file(string $from, string $to): bool
    {
        $uploaded = $GLOBALS['__album_controller_uploaded_files__'] ?? [];
        if (!in_array($from, $uploaded, true) || !is_file($from)) {
            return false;
        }

        $directory = dirname($to);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        return rename($from, $to);
    }
}

namespace Tests\Controllers;

use App\Albums\Application\UseCase\CreateAlbumUseCase;
use App\Albums\Application\UseCase\DeleteAlbumUseCase;
use App\Albums\Application\UseCase\FindAlbumUseCase;
use App\Albums\Application\UseCase\ListAlbumsUseCase;
use App\Albums\Application\UseCase\UpdateAlbumUseCase;
use App\Albums\Domain\Entity\Album;
use App\Shared\Infrastructure\Bus\InMemoryEventBus;
use PHPUnit\Framework\TestCase;
use Src\Controllers\AlbumController;
use Src\Controllers\Http\Request;
use Tests\Doubles\InMemoryAlbumRepository;
use Tests\Doubles\InMemoryHeroRepository;

final class AlbumControllerTest extends TestCase
{
    private AlbumController $controller;
    private InMemoryAlbumRepository $albumRepository;
    private InMemoryHeroRepository $heroRepository;
    private string $uploadDir;

    protected function setUp(): void
    {
        $this->albumRepository = new InMemoryAlbumRepository();
        $this->heroRepository = new InMemoryHeroRepository();

        $this->controller = new AlbumController(
            new ListAlbumsUseCase($this->albumRepository),
            new CreateAlbumUseCase($this->albumRepository),
            new UpdateAlbumUseCase($this->albumRepository, new InMemoryEventBus()),
            new DeleteAlbumUseCase($this->albumRepository, $this->heroRepository),
            new FindAlbumUseCase($this->albumRepository)
        );

        if (!defined('ALBUM_UPLOAD_DIR')) {
            define('ALBUM_UPLOAD_DIR', sys_get_temp_dir() . '/album-uploads');
        }
        if (!defined('ALBUM_UPLOAD_URL_PREFIX')) {
            define('ALBUM_UPLOAD_URL_PREFIX', 'https://cdn.example/');
        }
        if (!defined('ALBUM_COVER_MAX_BYTES')) {
            define('ALBUM_COVER_MAX_BYTES', 1024);
        }

        $this->uploadDir = rtrim((string) constant('ALBUM_UPLOAD_DIR'), DIRECTORY_SEPARATOR);
        @mkdir($this->uploadDir, 0777, true);

        http_response_code(200);
        $_FILES = [];
        $_GET = [];
        $GLOBALS['__album_controller_uploaded_files__'] = [];
    }

    protected function tearDown(): void
    {
        $this->cleanupDirectory($this->uploadDir);
    }

    public function testIndexReturnsAlbums(): void
    {
        $album = Album::create('album-1', 'Avengers', null);
        $this->albumRepository->save($album);

        $payload = $this->captureJson(fn () => $this->controller->index());

        self::assertSame('éxito', $payload['estado']);
        self::assertSame('Avengers', $payload['datos'][0]['nombre']);
    }

    public function testStoreCreatesAlbumFromRequestBody(): void
    {
        Request::withJsonBody(json_encode([
            'nombre' => 'Nuevos Vengadores',
            'coverImage' => 'https://example.com/cover.jpg',
        ], JSON_THROW_ON_ERROR));

        $payload = $this->captureJson(fn () => $this->controller->store());

        self::assertSame('éxito', $payload['estado']);
        self::assertNotSame('', $payload['datos']['albumId']);
        self::assertSame('Nuevos Vengadores', $payload['datos']['nombre']);
        self::assertSame(201, http_response_code());
        self::assertCount(1, $this->albumRepository->all());
    }

    public function testDestroyReturns404WhenAlbumMissing(): void
    {
        $payload = $this->captureJson(fn () => $this->controller->destroy('missing'));

        self::assertSame('error', $payload['estado']);
        self::assertSame(404, http_response_code());
    }

    public function testUploadCoverFailsWhenFileMissing(): void
    {
        $this->seedAlbum();

        $payload = $this->captureJson(fn () => $this->controller->uploadCover('album-1'));

        self::assertSame('error', $payload['estado']);
        self::assertSame('Archivo no proporcionado.', $payload['message']);
        self::assertSame(400, http_response_code());
    }

    public function testUploadCoverRejectsFilesLargerThanLimit(): void
    {
        $this->seedAlbum();
        $_FILES['file'] = [
            'name' => 'cover.png',
            'error' => UPLOAD_ERR_OK,
            'size' => constant('ALBUM_COVER_MAX_BYTES') + 1,
            'tmp_name' => __FILE__,
        ];

        $payload = $this->captureJson(fn () => $this->controller->uploadCover('album-1'));

        self::assertSame('error', $payload['estado']);
        self::assertSame(413, http_response_code());
        self::assertSame('El archivo excede el tamaño permitido (5MB).', $payload['message']);
    }

    public function testUploadCoverMovesFileAndUpdatesAlbum(): void
    {
        $this->seedAlbum();
        $tmpFile = tempnam(sys_get_temp_dir(), 'upload-') ?: sys_get_temp_dir() . '/upload-' . uniqid('', true);
        // PNG 1x1 minimal para que finfo detecte image/png
        $pngMinimal = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNgYAAAAAMAAWgmWQ0AAAAASUVORK5CYII=', true);
        file_put_contents($tmpFile, $pngMinimal === false ? 'binary-image' : $pngMinimal);
        $GLOBALS['__album_controller_uploaded_files__'][] = $tmpFile;

        $_FILES['file'] = [
            'name' => 'cover.png',
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpFile) ?: 512,
            'tmp_name' => $tmpFile,
        ];

        $payload = $this->captureJson(fn () => $this->controller->uploadCover('album-1'));

        self::assertSame('éxito', $payload['estado']);
        self::assertSame('album-1', $payload['datos']['albumId']);
        self::assertStringStartsWith('https://cdn.example/album-1-', $payload['datos']['coverImage']);
        self::assertSame(201, http_response_code());
        self::assertFileExists($this->uploadDir);
        $album = $this->albumRepository->find('album-1');
        self::assertNotNull($album);
        self::assertSame($payload['datos']['coverImage'], $album->coverImage());
    }

    /**
     * @return array<string, mixed>
     */
    private function captureJson(callable $callable): array
    {
        ob_start();
        $callable();
        $contents = (string) ob_get_clean();

        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }

    private function seedAlbum(): void
    {
        if ($this->albumRepository->find('album-1') !== null) {
            return;
        }

        $album = Album::create('album-1', 'Album base', null);
        $this->albumRepository->save($album);
    }

    private function cleanupDirectory(string $path): void
    {
        if (!is_dir($path)) {
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
            $target = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($target)) {
                $this->cleanupDirectory($target);
            } else {
                @unlink($target);
            }
        }

        @rmdir($path);
    }
}
