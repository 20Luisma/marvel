<?php

declare(strict_types=1);

namespace Tests\Bootstrap\Config;

use App\Bootstrap\Config\SecurityConfig;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SecurityConfigTest extends TestCase
{
    public function testCreateWithValidData(): void
    {
        $config = new SecurityConfig(
            adminEmail: 'admin@example.com',
            adminPasswordHash: password_hash('secret', PASSWORD_BCRYPT)
        );

        self::assertSame('admin@example.com', $config->adminEmail);
        self::assertSame(30, $config->sessionTtlMinutes);
        self::assertSame(8, $config->sessionLifetimeHours);
        self::assertTrue($config->csrfEnabled);
        self::assertFalse($config->antiReplayEnabled);
    }

    public function testCreateWithCustomValues(): void
    {
        $config = new SecurityConfig(
            adminEmail: 'custom@test.com',
            adminPasswordHash: 'hash',
            sessionTtlMinutes: 60,
            sessionLifetimeHours: 24,
            rateLimitMaxAttempts: 50,
            rateLimitWindowSeconds: 120,
            loginMaxAttempts: 3,
            loginBlockMinutes: 30,
            csrfEnabled: false,
            antiReplayEnabled: true
        );

        self::assertSame('custom@test.com', $config->adminEmail);
        self::assertSame(60, $config->sessionTtlMinutes);
        self::assertSame(24, $config->sessionLifetimeHours);
        self::assertSame(50, $config->rateLimitMaxAttempts);
        self::assertSame(120, $config->rateLimitWindowSeconds);
        self::assertSame(3, $config->loginMaxAttempts);
        self::assertSame(30, $config->loginBlockMinutes);
        self::assertFalse($config->csrfEnabled);
        self::assertTrue($config->antiReplayEnabled);
    }

    public function testRejectsEmptyEmail(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Admin email cannot be empty');

        new SecurityConfig(
            adminEmail: '',
            adminPasswordHash: 'hash'
        );
    }

    public function testRejectsInvalidEmail(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Admin email is not valid');

        new SecurityConfig(
            adminEmail: 'not-an-email',
            adminPasswordHash: 'hash'
        );
    }

    public function testRejectsEmptyPasswordHash(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Admin password hash cannot be empty');

        new SecurityConfig(
            adminEmail: 'admin@example.com',
            adminPasswordHash: '   '
        );
    }

    public function testRejectsZeroSessionTtl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Session TTL must be at least 1 minute');

        new SecurityConfig(
            adminEmail: 'admin@example.com',
            adminPasswordHash: 'hash',
            sessionTtlMinutes: 0
        );
    }

    public function testVerifyPasswordSuccess(): void
    {
        $password = 'mySecret123';
        $config = new SecurityConfig(
            adminEmail: 'admin@example.com',
            adminPasswordHash: password_hash($password, PASSWORD_BCRYPT)
        );

        self::assertTrue($config->verifyPassword($password));
    }

    public function testVerifyPasswordFailure(): void
    {
        $config = new SecurityConfig(
            adminEmail: 'admin@example.com',
            adminPasswordHash: password_hash('correct', PASSWORD_BCRYPT)
        );

        self::assertFalse($config->verifyPassword('wrong'));
    }

    public function testSessionTtlSeconds(): void
    {
        $config = new SecurityConfig(
            adminEmail: 'admin@example.com',
            adminPasswordHash: 'hash',
            sessionTtlMinutes: 45
        );

        self::assertSame(45 * 60, $config->sessionTtlSeconds());
    }

    public function testSessionLifetimeSeconds(): void
    {
        $config = new SecurityConfig(
            adminEmail: 'admin@example.com',
            adminPasswordHash: 'hash',
            sessionLifetimeHours: 12
        );

        self::assertSame(12 * 3600, $config->sessionLifetimeSeconds());
    }

    public function testLoginBlockSeconds(): void
    {
        $config = new SecurityConfig(
            adminEmail: 'admin@example.com',
            adminPasswordHash: 'hash',
            loginBlockMinutes: 20
        );

        self::assertSame(20 * 60, $config->loginBlockSeconds());
    }

    public function testToArrayExcludesPasswordHash(): void
    {
        $config = new SecurityConfig(
            adminEmail: 'admin@example.com',
            adminPasswordHash: 'super-secret-hash'
        );

        $array = $config->toArray();

        self::assertArrayHasKey('adminEmail', $array);
        self::assertArrayNotHasKey('adminPasswordHash', $array);
        self::assertSame('admin@example.com', $array['adminEmail']);
    }

    public function testForTestingCreatesValidConfig(): void
    {
        $config = SecurityConfig::forTesting();

        self::assertSame('test@example.com', $config->adminEmail);
        self::assertFalse($config->csrfEnabled);
        self::assertTrue($config->verifyPassword('test123'));
    }

    public function testImmutability(): void
    {
        $config = new SecurityConfig(
            adminEmail: 'admin@example.com',
            adminPasswordHash: 'hash'
        );

        // readonly properties cannot be modified after construction
        // This test verifies the class is readonly by checking property exists
        $reflection = new \ReflectionClass($config);
        
        self::assertTrue($reflection->isReadOnly());
    }

    public function testFromEnvUsesDefaults(): void
    {
        // Clear env vars to test defaults
        putenv('ADMIN_EMAIL');
        putenv('SECURITY_ADMIN_EMAIL');
        putenv('ADMIN_PASSWORD_HASH');
        putenv('SECURITY_ADMIN_PASSWORD_HASH');
        unset($_ENV['ADMIN_EMAIL'], $_ENV['SECURITY_ADMIN_EMAIL']);
        unset($_ENV['ADMIN_PASSWORD_HASH'], $_ENV['SECURITY_ADMIN_PASSWORD_HASH']);

        $config = SecurityConfig::fromEnv();

        self::assertSame('seguridadmarvel@gmail.com', $config->adminEmail);
        self::assertSame(30, $config->sessionTtlMinutes);
        self::assertSame(8, $config->sessionLifetimeHours);
    }

    public function testFromEnvReadsEnvVars(): void
    {
        $_ENV['ADMIN_EMAIL'] = 'env@test.com';
        $_ENV['ADMIN_PASSWORD_HASH'] = '$2y$12$testHash';
        $_ENV['SESSION_TTL_MINUTES'] = '45';
        $_ENV['SESSION_LIFETIME_HOURS'] = '12';
        $_ENV['RATE_LIMIT_MAX_ATTEMPTS'] = '200';
        $_ENV['CSRF_ENABLED'] = 'false';
        $_ENV['ANTI_REPLAY_ENABLED'] = '1';

        try {
            $config = SecurityConfig::fromEnv();

            self::assertSame('env@test.com', $config->adminEmail);
            self::assertSame(45, $config->sessionTtlMinutes);
            self::assertSame(12, $config->sessionLifetimeHours);
            self::assertSame(200, $config->rateLimitMaxAttempts);
            self::assertFalse($config->csrfEnabled);
            self::assertTrue($config->antiReplayEnabled);
        } finally {
            // Cleanup
            unset($_ENV['ADMIN_EMAIL'], $_ENV['ADMIN_PASSWORD_HASH']);
            unset($_ENV['SESSION_TTL_MINUTES'], $_ENV['SESSION_LIFETIME_HOURS']);
            unset($_ENV['RATE_LIMIT_MAX_ATTEMPTS'], $_ENV['CSRF_ENABLED'], $_ENV['ANTI_REPLAY_ENABLED']);
        }
    }

    public function testFromEnvUsesAlternativeKeys(): void
    {
        $_ENV['SECURITY_ADMIN_EMAIL'] = 'alt@test.com';
        $_ENV['SECURITY_ADMIN_PASSWORD_HASH'] = '$2y$12$altHash';

        try {
            $config = SecurityConfig::fromEnv();
            self::assertSame('alt@test.com', $config->adminEmail);
        } finally {
            unset($_ENV['SECURITY_ADMIN_EMAIL'], $_ENV['SECURITY_ADMIN_PASSWORD_HASH']);
        }
    }

    public function testRejectsZeroSessionLifetime(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Session lifetime must be at least 1 hour');

        new SecurityConfig(
            adminEmail: 'admin@example.com',
            adminPasswordHash: 'hash',
            sessionLifetimeHours: 0
        );
    }

    public function testRejectsZeroRateLimitAttempts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rate limit max attempts must be at least 1');

        new SecurityConfig(
            adminEmail: 'admin@example.com',
            adminPasswordHash: 'hash',
            rateLimitMaxAttempts: 0
        );
    }

    public function testRejectsZeroLoginAttempts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Login max attempts must be at least 1');

        new SecurityConfig(
            adminEmail: 'admin@example.com',
            adminPasswordHash: 'hash',
            loginMaxAttempts: 0
        );
    }

    public function testToArrayContainsAllPublicFields(): void
    {
        $config = new SecurityConfig(
            adminEmail: 'admin@example.com',
            adminPasswordHash: 'hash',
            sessionTtlMinutes: 45,
            sessionLifetimeHours: 12,
            rateLimitMaxAttempts: 50,
            rateLimitWindowSeconds: 120,
            loginMaxAttempts: 3,
            loginBlockMinutes: 30,
            csrfEnabled: false,
            antiReplayEnabled: true
        );

        $array = $config->toArray();

        self::assertSame(45, $array['sessionTtlMinutes']);
        self::assertSame(12, $array['sessionLifetimeHours']);
        self::assertSame(50, $array['rateLimitMaxAttempts']);
        self::assertSame(120, $array['rateLimitWindowSeconds']);
        self::assertSame(3, $array['loginMaxAttempts']);
        self::assertSame(30, $array['loginBlockMinutes']);
        self::assertFalse($array['csrfEnabled']);
        self::assertTrue($array['antiReplayEnabled']);
    }
}
