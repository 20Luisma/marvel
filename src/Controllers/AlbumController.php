<?php

declare(strict_types=1);

namespace Src\Controllers;

use App\Albums\Application\DTO\CreateAlbumRequest;
use App\Albums\Application\DTO\UpdateAlbumRequest;
use App\Albums\Application\UseCase\CreateAlbumUseCase;
use App\Albums\Application\UseCase\DeleteAlbumUseCase;
use App\Albums\Application\UseCase\FindAlbumUseCase;
use App\Albums\Application\UseCase\ListAlbumsUseCase;
use App\Albums\Application\UseCase\UpdateAlbumUseCase;
use App\Shared\Http\JsonResponse;
use InvalidArgumentException;
use RuntimeException;
use Src\Controllers\Helpers\DirectoryHelper;
use Src\Controllers\Http\Request;
use Throwable;

final class AlbumController
{
    public function __construct(
        private readonly ListAlbumsUseCase $listAlbums,
        private readonly CreateAlbumUseCase $createAlbum,
        private readonly UpdateAlbumUseCase $updateAlbum,
        private readonly DeleteAlbumUseCase $deleteAlbum,
        private readonly FindAlbumUseCase $findAlbum,
    ) {
    }

    public function index(): void
    {
        $data = $this->listAlbums->execute();
        JsonResponse::success($data);
    }

    public function store(): void
    {
        $payload = Request::jsonBody();
        $nombre = trim((string)($payload['nombre'] ?? ''));
        $coverImage = array_key_exists('coverImage', $payload) ? (string)$payload['coverImage'] : null;

        $response = $this->createAlbum->execute(new CreateAlbumRequest($nombre, $coverImage));
        JsonResponse::success($response->toArray(), 201);
    }

    public function update(string $albumId): void
    {
        $payload = Request::jsonBody();
        $nombre = array_key_exists('nombre', $payload) ? (string)$payload['nombre'] : null;
        $coverProvided = array_key_exists('coverImage', $payload);
        $coverImage = $coverProvided ? ($payload['coverImage'] ?? null) : null;

        $response = $this->updateAlbum->execute(new UpdateAlbumRequest($albumId, $nombre, $coverImage, $coverProvided));
        JsonResponse::success($response->toArray());
    }

    public function destroy(string $albumId): void
    {
        try {
            $this->deleteAlbum->execute($albumId);
            JsonResponse::success(['message' => 'Álbum eliminado.']);
        } catch (InvalidArgumentException $exception) {
            JsonResponse::error($exception->getMessage(), 404);
        }
    }

    public function uploadCover(string $albumId): void
    {
        try {
            $this->findAlbum->execute($albumId);
        } catch (InvalidArgumentException $exception) {
            JsonResponse::error($exception->getMessage(), 404);
            return;
        }

        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            JsonResponse::error('Archivo no proporcionado.', 400);
            return;
        }

        $file = $_FILES['file'];
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            JsonResponse::error('Error al subir el archivo.', 400);
            return;
        }

        $size = (int)($file['size'] ?? 0);
        if ($size <= 0) {
            JsonResponse::error('Archivo inválido.', 400);
            return;
        }

        if ($size > $this->albumCoverMaxBytes()) {
            JsonResponse::error('El archivo excede el tamaño permitido (5MB).', 413);
            return;
        }

        $originalName = (string)($file['name'] ?? '');
        $extension = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($extension, $allowedExtensions, true)) {
            JsonResponse::error('Formato de archivo no permitido.', 400);
            return;
        }

        $tmpPath = (string)($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            JsonResponse::error('Archivo temporal no válido.', 400);
            return;
        }

        $uploadDir = $this->albumUploadDir();

        try {
            DirectoryHelper::ensure($uploadDir);
        } catch (RuntimeException $exception) {
            JsonResponse::error($exception->getMessage(), 500);
            return;
        }

        $sanitizedAlbumId = preg_replace('/[^A-Za-z0-9\-]/', '', $albumId) ?: $albumId;
        try {
            $filename = sprintf('%s-%s.%s', $sanitizedAlbumId, bin2hex(random_bytes(6)), $extension);
        } catch (Throwable $exception) {
            JsonResponse::error('No se pudo generar el nombre del archivo.', 500);
            return;
        }

        $destination = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($tmpPath, $destination)) {
            JsonResponse::error('No se pudo guardar el archivo.', 500);
            return;
        }

        $coverUrl = $this->albumUploadUrlPrefix() . $filename;

        $this->updateAlbum->execute(new UpdateAlbumRequest($albumId, null, $coverUrl, true));

        JsonResponse::success([
            'albumId' => $albumId,
            'coverImage' => $coverUrl,
        ], 201);

        // TODO: mover la lógica de artefactos y file-system a src/Application/Albums/AlbumCoverUploadService.
    }

    /**
     * @return non-empty-string
     */
    private function albumUploadDir(): string
    {
        if (!defined('ALBUM_UPLOAD_DIR')) {
            throw new RuntimeException('ALBUM_UPLOAD_DIR no está definido.');
        }

        $value = constant('ALBUM_UPLOAD_DIR');
        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException('ALBUM_UPLOAD_DIR debe ser una cadena no vacía.');
        }

        return $value;
    }

    /**
     * @return non-empty-string
     */
    private function albumUploadUrlPrefix(): string
    {
        if (!defined('ALBUM_UPLOAD_URL_PREFIX')) {
            throw new RuntimeException('ALBUM_UPLOAD_URL_PREFIX no está definido.');
        }

        $value = constant('ALBUM_UPLOAD_URL_PREFIX');
        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException('ALBUM_UPLOAD_URL_PREFIX debe ser una cadena no vacía.');
        }

        return $value;
    }

    /**
     * @return positive-int
     */
    private function albumCoverMaxBytes(): int
    {
        if (!defined('ALBUM_COVER_MAX_BYTES')) {
            throw new RuntimeException('ALBUM_COVER_MAX_BYTES no está definido.');
        }

        $value = constant('ALBUM_COVER_MAX_BYTES');
        if (!is_int($value) || $value <= 0) {
            throw new RuntimeException('ALBUM_COVER_MAX_BYTES debe ser un entero positivo.');
        }

        return $value;
    }

}
