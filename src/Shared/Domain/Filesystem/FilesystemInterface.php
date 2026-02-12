<?php

declare(strict_types=1);

namespace App\Shared\Domain\Filesystem;

/**
 * Interfaz para abstraer el sistema de archivos.
 * 
 * Permite cambiar entre Local, AWS S3, Google Cloud Storage, etc.
 * sin modificar la lógica de negocio.
 */
interface FilesystemInterface
{
    /**
     * Guarda un archivo subido en el destino especificado.
     * 
     * @param string $tmpPath Ruta temporal del archivo (de $_FILES['tmp_name']).
     * @param string $destinationPath Ruta relativa o absoluta de destino.
     * @return bool True si se guardó correctamente.
     */
    public function saveUploadedFile(string $tmpPath, string $destinationPath): bool;

    /**
     * Asegura que un directorio existe.
     * 
     * @param string $directoryPath
     */
    public function ensureDirectory(string $directoryPath): void;

    /**
     * Obtiene la URL pública para un archivo dado.
     * 
     * @param string $filename
     * @return string
     */
    public function getPublicUrl(string $filename): string;
}
