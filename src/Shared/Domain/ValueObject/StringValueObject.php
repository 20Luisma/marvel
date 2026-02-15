<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use InvalidArgumentException;

abstract readonly class StringValueObject
{
    public function __construct(protected string $value)
    {
        $this->ensureIsValid($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(StringValueObject $other): bool
    {
        return static::class === get_class($other) && $this->value === $other->value;
    }

    protected function ensureIsValid(string $value): void
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException(sprintf('<%s> no puede estar vacío.', static::class));
        }
    }
}
