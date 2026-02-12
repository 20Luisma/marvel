<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Filesystem;

use App\Shared\Domain\Filesystem\FilesystemInterface;
use App\Controllers\Helpers\DirectoryHelper;
use RuntimeException;

/**
 * Implementación local del sistema de archivos.
 */
class LocalFilesystem implements FilesystemInterface
{
    public function __construct(
        private readonly string $basePath,
        private readonly string $urlPrefix
    ) {
    }

    public function saveUploadedFile(string $tmpPath, string $destinationPath): bool
    {
        $fullDestination = rtrim($this->basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($destinationPath, DIRECTORY_SEPARATOR);
        
        // El directorio padre debe existir
        $this->ensureDirectory(dirname($fullDestination));

        return move_uploaded_file($tmpPath, $fullDestination);
    }

    public function ensureDirectory(string $directoryPath): void
    {
        // Si es una ruta relativa, la hacemos absoluta basándonos en basePath
        $path = $directoryPath;
        if (strpos($path, $this->basePath) === false) {
            $path = rtrim($this->basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($directoryPath, DIRECTORY_SEPARATOR);
        }

        DirectoryHelper::ensure($path);
    }

    public function getPublicUrl(string $filename): string
    {
        return $this->urlPrefix . ltrim($filename, DIRECTORY_SEPARATOR);
    }
}
