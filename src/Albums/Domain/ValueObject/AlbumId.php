<?php

declare(strict_types=1);

namespace App\Albums\Domain\ValueObject;

use App\Shared\Domain\ValueObject\StringValueObject;

final readonly class AlbumId extends StringValueObject
{
    protected function ensureIsValid(string $value): void
    {
        if (trim($value) === '') {
            throw new \InvalidArgumentException('El id del álbum no puede estar vacío.');
        }

        if (!preg_match('/^[a-z0-9\-\_]+$/i', $value)) {
            throw new \InvalidArgumentException(sprintf('El formato del AlbumId <%s> es inválido.', $value));
        }
    }
}

