<?php

declare(strict_types=1);

namespace Tests\Controllers;

use App\Controllers\AlbumController;
use App\Controllers\Http\Request;
use App\Albums\Application\UseCase\ListAlbumsUseCase;
use App\Albums\Application\UseCase\CreateAlbumUseCase;
use App\Albums\Application\UseCase\UpdateAlbumUseCase;
use App\Albums\Application\UseCase\DeleteAlbumUseCase;
use App\Albums\Application\UseCase\FindAlbumUseCase;
use App\Albums\Domain\Repository\AlbumRepository;
use App\Albums\Domain\Entity\Album;
use App\Heroes\Domain\Repository\HeroRepository;
use App\Shared\Domain\Bus\EventBus;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

/**
 * Additional coverage tests for AlbumController
 */
class AlbumControllerUploadTest extends TestCase
{
    private $repository;
    private $eventBus;
    private $heroRepository;
    private AlbumController $controller;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AlbumRepository::class);
        $this->eventBus = $this->createMock(EventBus::class);
        $this->heroRepository = $this->createMock(HeroRepository::class);
        
        $this->controller = new AlbumController(
            new ListAlbumsUseCase($this->repository),
            new CreateAlbumUseCase($this->repository),
            new UpdateAlbumUseCase($this->repository, $this->eventBus),
            new DeleteAlbumUseCase($this->repository, $this->heroRepository),
            new FindAlbumUseCase($this->repository)
        );
    }

    public function testDestroySuccess(): void
    {
        $albumId = 'album-to-delete';
        $album = Album::create($albumId, 'Test Album', null);
        
        $this->repository->method('find')->willReturn($album);
        $this->heroRepository->method('byAlbum')->willReturn([]);
        $this->repository->expects($this->once())->method('delete');

        $payload = $this->capturePayload(fn () => $this->controller->destroy($albumId));

        $this->assertSame('éxito', $payload['estado']);
        $this->assertStringContainsString('eliminado', $payload['datos']['message'] ?? '');
    }

    public function testDestroyNotFound(): void
    {
        $this->repository->method('find')->willThrowException(
            new InvalidArgumentException('Album not found')
        );

        $payload = $this->capturePayload(fn () => $this->controller->destroy('non-existent'));

        $this->assertSame('error', $payload['estado']);
    }

    public function testUploadCoverWithoutFile(): void
    {
        $albumId = 'album-123';
        $album = Album::create($albumId, 'Test Album', null);
        
        $this->repository->method('find')->willReturn($album);
        
        $_FILES = [];

        $payload = $this->capturePayload(fn () => $this->controller->uploadCover($albumId));

        $this->assertSame('error', $payload['estado']);
        $this->assertStringContainsString('Archivo no proporcionado', $payload['message'] ?? '');
    }

    public function testUploadCoverAlbumNotFound(): void
    {
        $this->repository->method('find')->willThrowException(
            new InvalidArgumentException('Album not found')
        );

        $_FILES = [];

        $payload = $this->capturePayload(fn () => $this->controller->uploadCover('non-existent'));

        $this->assertSame('error', $payload['estado']);
    }

    public function testUploadCoverWithUploadError(): void
    {
        $albumId = 'album-123';
        $album = Album::create($albumId, 'Test Album', null);
        
        $this->repository->method('find')->willReturn($album);
        
        $_FILES = [
            'file' => [
                'name' => 'test.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => '/tmp/test.jpg',
                'error' => UPLOAD_ERR_PARTIAL,
                'size' => 1000,
            ],
        ];

        $payload = $this->capturePayload(fn () => $this->controller->uploadCover($albumId));

        $this->assertSame('error', $payload['estado']);
        $this->assertStringContainsString('Error al subir', $payload['message'] ?? '');
    }

    public function testUploadCoverWithZeroSize(): void
    {
        $albumId = 'album-123';
        $album = Album::create($albumId, 'Test Album', null);
        
        $this->repository->method('find')->willReturn($album);
        
        $_FILES = [
            'file' => [
                'name' => 'test.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => '/tmp/test.jpg',
                'error' => UPLOAD_ERR_OK,
                'size' => 0,
            ],
        ];

        $payload = $this->capturePayload(fn () => $this->controller->uploadCover($albumId));

        $this->assertSame('error', $payload['estado']);
        $this->assertStringContainsString('inválido', $payload['message'] ?? '');
    }

    public function testUploadCoverWithInvalidExtension(): void
    {
        $albumId = 'album-123';
        $album = Album::create($albumId, 'Test Album', null);
        
        $this->repository->method('find')->willReturn($album);
        
        // Define constants if not defined
        if (!defined('ALBUM_COVER_MAX_BYTES')) {
            define('ALBUM_COVER_MAX_BYTES', 5242880);
        }
        
        $_FILES = [
            'file' => [
                'name' => 'test.exe',
                'type' => 'application/x-executable',
                'tmp_name' => '/tmp/test.exe',
                'error' => UPLOAD_ERR_OK,
                'size' => 1000,
            ],
        ];

        $payload = $this->capturePayload(fn () => $this->controller->uploadCover($albumId));

        $this->assertSame('error', $payload['estado']);
        $this->assertStringContainsString('Formato de archivo no permitido', $payload['message'] ?? '');
    }

    public function testStoreWithMissingNombre(): void
    {
        Request::withJsonBody(json_encode(['foo' => 'bar']));

        $payload = $this->capturePayload(fn () => $this->controller->store());

        $this->assertSame('error', $payload['estado']);
    }

    public function testStoreWithCoverImage(): void
    {
        $this->repository->expects($this->once())->method('save');
        
        Request::withJsonBody(json_encode([
            'nombre' => 'Album with Cover',
            'coverImage' => 'http://example.com/cover.jpg',
        ]));

        $payload = $this->capturePayload(fn () => $this->controller->store());

        $this->assertSame('éxito', $payload['estado']);
    }

    public function testUpdateWithNombre(): void
    {
        $albumId = 'album-to-update';
        $album = Album::create($albumId, 'Original Name', null);
        
        $this->repository->method('find')->willReturn($album);
        $this->repository->expects($this->once())->method('save');
        
        Request::withJsonBody(json_encode(['nombre' => 'Updated Name']));

        $payload = $this->capturePayload(fn () => $this->controller->update($albumId));

        $this->assertSame('éxito', $payload['estado']);
    }

    public function testUpdateWithCoverImage(): void
    {
        $albumId = 'album-to-update';
        $album = Album::create($albumId, 'Test Album', null);
        
        $this->repository->method('find')->willReturn($album);
        $this->repository->expects($this->once())->method('save');
        
        Request::withJsonBody(json_encode(['coverImage' => 'http://example.com/new-cover.jpg']));

        $payload = $this->capturePayload(fn () => $this->controller->update($albumId));

        $this->assertSame('éxito', $payload['estado']);
    }

    public function testUpdateWithBothNombreAndCover(): void
    {
        $albumId = 'album-to-update';
        $album = Album::create($albumId, 'Test Album', null);
        
        $this->repository->method('find')->willReturn($album);
        $this->repository->expects($this->once())->method('save');
        
        Request::withJsonBody(json_encode([
            'nombre' => 'New Name',
            'coverImage' => 'http://example.com/new-cover.jpg',
        ]));

        $payload = $this->capturePayload(fn () => $this->controller->update($albumId));

        $this->assertSame('éxito', $payload['estado']);
    }

    public function testUpdateWithNullCoverImage(): void
    {
        $albumId = 'album-to-update';
        $album = Album::create($albumId, 'Test Album', 'http://example.com/old-cover.jpg');
        
        $this->repository->method('find')->willReturn($album);
        $this->repository->expects($this->once())->method('save');
        
        Request::withJsonBody(json_encode(['coverImage' => null]));

        $payload = $this->capturePayload(fn () => $this->controller->update($albumId));

        $this->assertSame('éxito', $payload['estado']);
    }

    public function testUploadCoverWithFileTooLarge(): void
    {
        $albumId = 'album-123';
        $album = Album::create($albumId, 'Test Album', null);
        
        $this->repository->method('find')->willReturn($album);
        
        // Define the constant if not defined
        if (!defined('ALBUM_COVER_MAX_BYTES')) {
            define('ALBUM_COVER_MAX_BYTES', 5242880);
        }
        
        $_FILES = [
            'file' => [
                'name' => 'large.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => '/tmp/large.jpg',
                'error' => UPLOAD_ERR_OK,
                'size' => 10000000, // 10MB, larger than 5MB limit
            ],
        ];

        $payload = $this->capturePayload(fn () => $this->controller->uploadCover($albumId));

        $this->assertSame('error', $payload['estado']);
        $this->assertStringContainsString('tamaño permitido', $payload['message'] ?? '');
    }

    public function testUploadCoverWithInvalidTemporaryFile(): void
    {
        $albumId = 'album-123';
        $album = Album::create($albumId, 'Test Album', null);
        
        $this->repository->method('find')->willReturn($album);
        
        if (!defined('ALBUM_COVER_MAX_BYTES')) {
            define('ALBUM_COVER_MAX_BYTES', 5242880);
        }
        
        $_FILES = [
            'file' => [
                'name' => 'test.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => '', // Empty tmp_name
                'error' => UPLOAD_ERR_OK,
                'size' => 1000,
            ],
        ];

        $payload = $this->capturePayload(fn () => $this->controller->uploadCover($albumId));

        $this->assertSame('error', $payload['estado']);
        $this->assertStringContainsString('temporal no válido', $payload['message'] ?? '');
    }

    public function testStoreWithSuspiciousInput(): void
    {
        $this->repository->expects($this->once())->method('save');
        
        // Input with potential XSS/injection patterns
        Request::withJsonBody(json_encode([
            'nombre' => '<script>alert("xss")</script>Album Name',
        ]));

        $payload = $this->capturePayload(fn () => $this->controller->store());

        // Should sanitize and still succeed
        $this->assertSame('éxito', $payload['estado']);
    }

    public function testStoreWithSpecialCharactersInName(): void
    {
        $this->repository->expects($this->once())->method('save');
        
        Request::withJsonBody(json_encode([
            'nombre' => 'Álbum con ñ y acentos: áéíóú',
        ]));

        $payload = $this->capturePayload(fn () => $this->controller->store());

        $this->assertSame('éxito', $payload['estado']);
    }

    public function testStoreWithWhitespaceOnlyName(): void
    {
        Request::withJsonBody(json_encode([
            'nombre' => '   ',
        ]));

        $payload = $this->capturePayload(fn () => $this->controller->store());

        $this->assertSame('error', $payload['estado']);
    }

    public function testUpdateWithEmptyPayload(): void
    {
        $albumId = 'album-to-update';
        $album = Album::create($albumId, 'Test Album', null);
        
        $this->repository->method('find')->willReturn($album);
        // With empty payload, save might still be called to persist the existing state
        $this->repository->method('save');
        
        Request::withJsonBody('{}');

        $payload = $this->capturePayload(fn () => $this->controller->update($albumId));

        $this->assertSame('éxito', $payload['estado']);
    }

    public function testIndexReturnsEmptyListWhenNoAlbums(): void
    {
        $this->repository->method('all')->willReturn([]);

        $payload = $this->capturePayload(fn () => $this->controller->index());

        $this->assertSame('éxito', $payload['estado']);
        $this->assertSame([], $payload['datos']);
    }

    public function testIndexReturnsListOfAlbums(): void
    {
        $albums = [
            Album::create('album-1', 'Album 1', null),
            Album::create('album-2', 'Album 2', 'http://example.com/cover.jpg'),
        ];
        
        $this->repository->method('all')->willReturn($albums);

        $payload = $this->capturePayload(fn () => $this->controller->index());

        $this->assertSame('éxito', $payload['estado']);
        $this->assertCount(2, $payload['datos']);
    }

    /**
     * @return array<string, mixed>
     */
    private function capturePayload(callable $callable): array
    {
        ob_start();
        $result = $callable();
        $contents = (string) ob_get_clean();

        $payload = \App\Shared\Http\JsonResponse::lastPayload();

        if (is_array($result)) {
            return $result;
        }

        if ($payload !== null) {
            return $payload;
        }

        if ($contents !== '') {
            return json_decode($contents, true) ?? [];
        }

        return [];
    }
}
