<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Config\SecurityConfig;
use App\Security\Auth\AuthService;
use App\Security\Logging\SecurityLogger;
use PHPUnit\Framework\TestCase;

final class SessionLifetimeTest extends TestCase
{
    private string $logFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logFile = dirname(__DIR__, 2) . '/storage/logs/session_lifetime_test.log';
        if (is_file($this->logFile)) {
            @unlink($this->logFile);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_id('sess-life-' . uniqid());
        session_start();
        $_SESSION = [];

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit Lifetime Agent';
        $_SERVER['REQUEST_URI'] = '/secret/sonar';
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        parent::tearDown();
    }

    public function testSessionExpiresAfterMaxLifetime(): void
    {
        $_SESSION = [
            'auth' => [
                'user_id' => 'marvel-admin',
                'role' => 'admin',
                'email' => 'seguridadmarvel@gmail.com',
                'last_activity' => time(),
            ],
            'session_created_at' => time() - 29000, // > 8h
            'session_ip_hash' => hash('sha256', '127.0.0.1'),
            'session_ua_hash' => hash('sha256', 'PHPUnit Lifetime Agent'),
        ];

        $service = new AuthService(new SecurityConfig(), new SecurityLogger($this->logFile));

        self::assertFalse($service->isAuthenticated());
        self::assertFileExists($this->logFile);
        $log = (string) file_get_contents($this->logFile);
        self::assertStringContainsString('session_expired_lifetime', $log);
    }
}
