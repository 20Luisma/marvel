<?php

declare(strict_types=1);

namespace App\Bootstrap\Config;

use InvalidArgumentException;

/**
 * Immutable Value Object containing security configuration.
 * 
 * Replaces array<string, mixed> with typed, validated configuration.
 * Follows Value Object pattern: immutable, self-validating, equality by value.
 */
final readonly class SecurityConfig
{
    /**
     * @param string $adminEmail Admin user email
     * @param string $adminPasswordHash Bcrypt hash of admin password
     * @param int $sessionTtlMinutes Session inactivity timeout in minutes
     * @param int $sessionLifetimeHours Maximum session lifetime in hours
     * @param int $rateLimitMaxAttempts Max requests before rate limiting kicks in
     * @param int $rateLimitWindowSeconds Time window for rate limiting
     * @param int $loginMaxAttempts Max failed login attempts before blocking
     * @param int $loginBlockMinutes How long to block after max attempts
     * @param bool $csrfEnabled Whether CSRF protection is active
     * @param bool $antiReplayEnabled Whether anti-replay protection is active
     */
    public function __construct(
        public string $adminEmail,
        public string $adminPasswordHash,
        public int $sessionTtlMinutes = 30,
        public int $sessionLifetimeHours = 8,
        public int $rateLimitMaxAttempts = 100,
        public int $rateLimitWindowSeconds = 60,
        public int $loginMaxAttempts = 5,
        public int $loginBlockMinutes = 15,
        public bool $csrfEnabled = true,
        public bool $antiReplayEnabled = false,
    ) {
        $this->validate();
    }

    /**
     * Create SecurityConfig from environment variables.
     * 
     * Required env vars:
     * - ADMIN_EMAIL (or SECURITY_ADMIN_EMAIL)
     * - ADMIN_PASSWORD_HASH (or SECURITY_ADMIN_PASSWORD_HASH)
     * 
     * Optional env vars with defaults:
     * - SESSION_TTL_MINUTES (30)
     * - SESSION_LIFETIME_HOURS (8)
     * - RATE_LIMIT_MAX_ATTEMPTS (100)
     * - RATE_LIMIT_WINDOW_SECONDS (60)
     * - LOGIN_MAX_ATTEMPTS (5)
     * - LOGIN_BLOCK_MINUTES (15)
     * - CSRF_ENABLED (true)
     * - ANTI_REPLAY_ENABLED (false)
     */
    public static function fromEnv(): self
    {
        $adminEmail = self::getEnvString('ADMIN_EMAIL', 'SECURITY_ADMIN_EMAIL') 
            ?? 'seguridadmarvel@gmail.com';
            
        $adminPasswordHash = self::getEnvString('ADMIN_PASSWORD_HASH', 'SECURITY_ADMIN_PASSWORD_HASH')
            ?? '$2y$12$defaultHashForDevelopmentOnly';

        return new self(
            adminEmail: $adminEmail,
            adminPasswordHash: $adminPasswordHash,
            sessionTtlMinutes: self::getEnvInt('SESSION_TTL_MINUTES', 30),
            sessionLifetimeHours: self::getEnvInt('SESSION_LIFETIME_HOURS', 8),
            rateLimitMaxAttempts: self::getEnvInt('RATE_LIMIT_MAX_ATTEMPTS', 100),
            rateLimitWindowSeconds: self::getEnvInt('RATE_LIMIT_WINDOW_SECONDS', 60),
            loginMaxAttempts: self::getEnvInt('LOGIN_MAX_ATTEMPTS', 5),
            loginBlockMinutes: self::getEnvInt('LOGIN_BLOCK_MINUTES', 15),
            csrfEnabled: self::getEnvBool('CSRF_ENABLED', true),
            antiReplayEnabled: self::getEnvBool('ANTI_REPLAY_ENABLED', false),
        );
    }

    /**
     * Create config for test environment with sensible defaults.
     */
    public static function forTesting(): self
    {
        return new self(
            adminEmail: 'test@example.com',
            adminPasswordHash: password_hash('test123', PASSWORD_BCRYPT),
            sessionTtlMinutes: 60,
            sessionLifetimeHours: 24,
            rateLimitMaxAttempts: 1000,
            rateLimitWindowSeconds: 60,
            loginMaxAttempts: 100,
            loginBlockMinutes: 1,
            csrfEnabled: false,
            antiReplayEnabled: false,
        );
    }

    /**
     * Check if password matches the stored hash.
     */
    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->adminPasswordHash);
    }

    /**
     * Get session TTL in seconds.
     */
    public function sessionTtlSeconds(): int
    {
        return $this->sessionTtlMinutes * 60;
    }

    /**
     * Get session lifetime in seconds.
     */
    public function sessionLifetimeSeconds(): int
    {
        return $this->sessionLifetimeHours * 3600;
    }

    /**
     * Get login block duration in seconds.
     */
    public function loginBlockSeconds(): int
    {
        return $this->loginBlockMinutes * 60;
    }

    /**
     * @return array<string, mixed> Serializable representation (without sensitive data)
     */
    public function toArray(): array
    {
        return [
            'adminEmail' => $this->adminEmail,
            'sessionTtlMinutes' => $this->sessionTtlMinutes,
            'sessionLifetimeHours' => $this->sessionLifetimeHours,
            'rateLimitMaxAttempts' => $this->rateLimitMaxAttempts,
            'rateLimitWindowSeconds' => $this->rateLimitWindowSeconds,
            'loginMaxAttempts' => $this->loginMaxAttempts,
            'loginBlockMinutes' => $this->loginBlockMinutes,
            'csrfEnabled' => $this->csrfEnabled,
            'antiReplayEnabled' => $this->antiReplayEnabled,
        ];
    }

    private function validate(): void
    {
        if (trim($this->adminEmail) === '') {
            throw new InvalidArgumentException('Admin email cannot be empty');
        }

        if (!filter_var($this->adminEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Admin email is not valid: ' . $this->adminEmail);
        }

        if (trim($this->adminPasswordHash) === '') {
            throw new InvalidArgumentException('Admin password hash cannot be empty');
        }

        if ($this->sessionTtlMinutes < 1) {
            throw new InvalidArgumentException('Session TTL must be at least 1 minute');
        }

        if ($this->sessionLifetimeHours < 1) {
            throw new InvalidArgumentException('Session lifetime must be at least 1 hour');
        }

        if ($this->rateLimitMaxAttempts < 1) {
            throw new InvalidArgumentException('Rate limit max attempts must be at least 1');
        }

        if ($this->loginMaxAttempts < 1) {
            throw new InvalidArgumentException('Login max attempts must be at least 1');
        }
    }

    private static function getEnvString(string ...$keys): ?string
    {
        foreach ($keys as $key) {
            $value = $_ENV[$key] ?? getenv($key);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }
        return null;
    }

    private static function getEnvInt(string $key, int $default): int
    {
        $value = $_ENV[$key] ?? getenv($key);
        if (is_numeric($value)) {
            return (int) $value;
        }
        return $default;
    }

    private static function getEnvBool(string $key, bool $default): bool
    {
        $envValue = $_ENV[$key] ?? null;
        $getEnvValue = getenv($key);
        
        $value = $envValue ?? ($getEnvValue !== false ? $getEnvValue : null);
        
        if ($value === null || $value === '') {
            return $default;
        }
        
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }
}
