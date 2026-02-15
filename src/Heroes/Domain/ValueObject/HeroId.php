<?php

declare(strict_types=1);

namespace App\Heroes\Domain\ValueObject;

use App\Shared\Domain\ValueObject\StringValueObject;

final readonly class HeroId extends StringValueObject
{
    protected function ensureIsValid(string $value): void
    {
        if (trim($value) === '') {
            throw new \InvalidArgumentException('El id del héroe no puede estar vacío.');
        }

        if (!preg_match('/^[a-z0-9\-\_]+$/i', $value)) {
            throw new \InvalidArgumentException(sprintf('El formato del HeroId <%s> es inválido (solo alfanuméricos y guiones).', $value));
        }
    }
}

