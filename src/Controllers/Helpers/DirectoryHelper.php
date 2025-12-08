<?php

declare(strict_types=1);

namespace App\Controllers\Helpers;

use RuntimeException;

final class DirectoryHelper
{
    public static function ensure(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (is_file($path)) {
            throw new RuntimeException(sprintf('No se pudo crear el directorio: %s', $path));
        }

        if (!@mkdir($path, 0777, true) && !is_dir($path)) {
            throw new RuntimeException(sprintf('No se pudo crear el directorio: %s', $path));
        }
    }
}
