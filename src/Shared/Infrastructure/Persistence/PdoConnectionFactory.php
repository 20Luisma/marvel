<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use PDO;
use PDOException;

final class PdoConnectionFactory
{
    public static function fromEnvironment(): PDO
    {
        $host = self::env('DB_HOST', '127.0.0.1');
        $port = self::env('DB_PORT', '3306');
        $dbName = self::env('DB_NAME', '');
        $user = self::env('DB_USER', 'root');
        $password = self::env('DB_PASSWORD', self::env('DB_PASS', ''));
        $charset = self::env('DB_CHARSET', 'utf8mb4');

        if ($dbName === '') {
            throw new PDOException('DB_NAME no está configurado.');
        }

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $dbName, $charset);

        return new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    private static function env(string $key, string $default = ''): string
    {
        $value = $_ENV[$key] ?? getenv($key);

        if (!is_string($value)) {
            return $default;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? $default : $trimmed;
    }
}
