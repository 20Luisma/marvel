<?php

declare(strict_types=1);

namespace App\Security\Validation;

use InvalidArgumentException;

final class JsonValidator
{
    /**
     * @param array<string, array{type: string, required?: bool}> $schema
     */
    public function validate(array $payload, array $schema, bool $allowEmpty = false): void
    {
        if (!$allowEmpty && $payload === []) {
            throw new InvalidArgumentException('El payload es obligatorio.');
        }

        foreach ($schema as $field => $rules) {
            $required = $rules['required'] ?? true;
            $type = $rules['type'] ?? 'string';

            $exists = array_key_exists($field, $payload);
            if ($required && !$exists) {
                throw new InvalidArgumentException(sprintf('El campo %s es obligatorio.', $field));
            }

            if (!$exists) {
                continue;
            }

            $value = $payload[$field];
            if (!$this->matchesType($value, $type)) {
                throw new InvalidArgumentException(sprintf('Tipo inválido para %s.', $field));
            }
        }
    }

    private function matchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'array' => is_array($value),
            'int' => is_int($value),
            'float' => is_float($value) || is_int($value),
            'bool' => is_bool($value),
            default => false,
        };
    }
}
