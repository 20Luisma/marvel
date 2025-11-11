<?php

declare(strict_types=1);

namespace App\Activities\Domain;

final class ActivityScope
{
    public const ALBUMS = 'albums';
    public const HEROES = 'heroes';
    public const COMIC = 'comic';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::ALBUMS,
            self::HEROES,
            self::COMIC,
        ];
    }

    public static function assertValid(string $scope): string
    {
        $normalized = strtolower(trim($scope));

        if (!in_array($normalized, self::all(), true)) {
            throw new \InvalidArgumentException(sprintf('Scope de actividad no soportado: %s', $scope));
        }

        return $normalized;
    }

    public static function requiresContext(string $scope): bool
    {
        return $scope === self::HEROES;
    }

    public static function normalizeContext(string $scope, mixed $contextId): ?string
    {
        if (!self::requiresContext($scope)) {
            return null;
        }

        $value = is_scalar($contextId) ? trim((string) $contextId) : '';

        if ($value === '') {
            throw new \InvalidArgumentException('El contexto es obligatorio para el scope de héroes.');
        }

        return mb_substr($value, 0, 120);
    }

    /**
     * Convierte un scope + contexto en un nombre de archivo seguro.
     */
    public static function fileName(string $scope, ?string $contextId = null): string
    {
        $base = self::assertValid($scope);

        if ($contextId === null || $contextId === '') {
            return $base . '.json';
        }

        $sanitized = preg_replace('/[^A-Za-z0-9_\-]/', '-', $contextId) ?? 'context';

        return $base . '-' . $sanitized . '.json';
    }
}
