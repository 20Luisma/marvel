<?php

declare(strict_types=1);

namespace Tests\Shared\Infrastructure;

use App\Shared\Infrastructure\Persistence\PdoConnectionFactory;
use PDOException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class PdoConnectionFactoryTest extends TestCase
{
    /** @var array<string, string|false|null> */
    private array $backupEnv = [];

    protected function setUp(): void
    {
        $this->backupEnv = [
            'DB_HOST' => getenv('DB_HOST'),
            'DB_PORT' => getenv('DB_PORT'),
            'DB_NAME' => getenv('DB_NAME'),
            'DB_USER' => getenv('DB_USER'),
            'DB_PASSWORD' => getenv('DB_PASSWORD'),
            'DB_PASS' => getenv('DB_PASS'),
            'DB_CHARSET' => getenv('DB_CHARSET'),
        ];
    }

    protected function tearDown(): void
    {
        foreach ($this->backupEnv as $key => $value) {
            if ($value === false || $value === null) {
                putenv($key);
                unset($_ENV[$key]);
            } else {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
            }
        }
    }

    // Lanza una excepción descriptiva cuando falta DB_NAME.
    public function testItThrowsWhenDatabaseNameIsMissing(): void
    {
        putenv('DB_NAME');
        unset($_ENV['DB_NAME']);

        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('DB_NAME no está configurado.');

        PdoConnectionFactory::fromEnvironment();
    }

    // Aplica trim y fallback por defecto a las variables de entorno.
    public function testEnvHelperReturnsDefaultWhenValueIsEmpty(): void
    {
        putenv('DB_HOST=   ');
        $_ENV['DB_HOST'] = '   ';

        $envMethod = new ReflectionMethod(PdoConnectionFactory::class, 'env');
        $envMethod->setAccessible(true);

        $host = $envMethod->invoke(null, 'DB_HOST', 'fallback-host');

        self::assertSame('fallback-host', $host);
    }

    // Propaga errores de conexión al construir el PDO con credenciales inválidas.
    public function testItPropagatesConnectionErrors(): void
    {
        putenv('DB_HOST=localhost');
        putenv('DB_PORT=3306');
        putenv('DB_NAME=fake_database_for_tests');
        putenv('DB_USER=invalid_user');
        putenv('DB_PASSWORD=invalid_pass');

        $_ENV['DB_HOST'] = 'localhost';
        $_ENV['DB_PORT'] = '3306';
        $_ENV['DB_NAME'] = 'fake_database_for_tests';
        $_ENV['DB_USER'] = 'invalid_user';
        $_ENV['DB_PASSWORD'] = 'invalid_pass';

        $this->expectException(PDOException::class);

        PdoConnectionFactory::fromEnvironment();
    }
}
