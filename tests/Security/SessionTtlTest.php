<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Config\SecurityConfig;
use App\Security\Auth\AuthService;
use App\Security\Logging\SecurityLogger;
use PHPUnit\Framework\TestCase;

final class SessionTtlTest extends TestCase
{
    private string $logFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logFile = dirname(__DIR__, 2) . '/storage/logs/session_ttl_test.log';
        if (is_file($this->logFile)) {
            @unlink($this->logFile);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_id('sess-ttl-' . uniqid());
        session_start();
        $_SESSION = [];

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit TTL Test Agent';
        $_SERVER['REQUEST_URI'] = '/secret/sonar';
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        parent::tearDown();
    }

    public function testSessionExpiresAfterInactivity(): void
    {
        $_SESSION = [
            'auth' => [
                'user_id' => 'marvel-admin',
                'role' => 'admin',
                'email' => 'seguridadmarvel@gmail.com',
                'last_activity' => time() - 1900, // > 30min
            ],
            'session_created_at' => time(),
            'session_ip_hash' => hash('sha256', '127.0.0.1'),
            'session_ua_hash' => hash('sha256', 'PHPUnit TTL Test Agent'),
        ];

        $service = new AuthService(new SecurityConfig(), new SecurityLogger($this->logFile));

        self::assertFalse($service->isAuthenticated());
        self::assertFileExists($this->logFile);
        $log = (string) file_get_contents($this->logFile);
        self::assertStringContainsString('session_expired_ttl', $log);
    }

    public function testSessionRenewsLastActivityOnValidRequest(): void
    {
        $now = time() - 60;
        $_SESSION = [
            'auth' => [
                'user_id' => 'marvel-admin',
                'role' => 'admin',
                'email' => 'seguridadmarvel@gmail.com',
                'last_activity' => $now,
            ],
            'session_created_at' => time(),
            'session_ip_hash' => hash('sha256', '127.0.0.1'),
            'session_ua_hash' => hash('sha256', 'PHPUnit TTL Test Agent'),
        ];

        $service = new AuthService(new SecurityConfig(), new SecurityLogger($this->logFile));

        self::assertTrue($service->isAuthenticated());
        self::assertGreaterThan($now, $_SESSION['auth']['last_activity']);
    }
}
