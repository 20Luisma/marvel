<?php

declare(strict_types=1);

namespace App\Albums\Application\UseCase;

use App\Shared\Domain\Filesystem\FilesystemInterface;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Caso de uso para gestionar la subida de portadas de álbumes.
 * 
 * Orquesta la validación básica, la generación de nombres únicos
 * y el almacenamiento desacoplado de archivos.
 */
final class UploadAlbumCoverUseCase
{
    public function __construct(
        private readonly FilesystemInterface $filesystem,
        private readonly FindAlbumUseCase $findAlbum,
        private readonly UpdateAlbumUseCase $updateAlbum
    ) {
    }

    /**
     * @param string $albumId
     * @param string $tmpPath Ruta temporal del archivo.
     * @param string $extension Extensión del archivo (jpg, png, etc).
     * @return array{albumId: string, coverImage: string}
     * @throws InvalidArgumentException Si el álbum no existe o los datos son inválidos.
     * @throws RuntimeException Si ocurre un error guardando el archivo.
     */
    public function execute(string $albumId, string $tmpPath, string $extension): array
    {
        // 1. Validar que el álbum existe
        $this->findAlbum->execute($albumId);

        // 2. Preparar el nombre del archivo
        $sanitizedAlbumId = preg_replace('/[^A-Za-z0-9\-]/', '', $albumId) ?: $albumId;
        try {
            $filename = sprintf('%s-%s.%s', $sanitizedAlbumId, bin2hex(random_bytes(6)), $extension);
        } catch (Throwable) {
            throw new RuntimeException('No se pudo generar el nombre del archivo.');
        }

        // 3. Guardar el archivo usando la abstracción
        // En este sistema, guardamos en la raíz del storage configurado
        if (!$this->filesystem->saveUploadedFile($tmpPath, $filename)) {
            throw new RuntimeException('No se pudo guardar el archivo en el sistema de archivos.');
        }

        // 4. Obtener la URL pública
        $coverUrl = $this->filesystem->getPublicUrl($filename);

        // 5. Actualizar el álbum
        $this->updateAlbum->execute(new \App\Albums\Application\DTO\UpdateAlbumRequest(
            $albumId, 
            null, 
            $coverUrl, 
            true
        ));

        return [
            'albumId' => $albumId,
            'coverImage' => $coverUrl,
        ];
    }
}
