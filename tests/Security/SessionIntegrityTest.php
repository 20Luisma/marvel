<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Config\SecurityConfig;
use App\Security\Auth\AuthService;
use App\Security\Logging\SecurityLogger;
use PHPUnit\Framework\TestCase;

final class SessionIntegrityTest extends TestCase
{
    private string $logFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logFile = dirname(__DIR__, 2) . '/storage/logs/session_integrity_test.log';
        if (is_file($this->logFile)) {
            @unlink($this->logFile);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_id('sess-int-' . uniqid());
        session_start();
        $_SESSION = [];

        $_SERVER['REQUEST_URI'] = '/secret/sonar';
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        parent::tearDown();
    }

    public function testChangingUserAgentForcesLogout(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'Original Agent';

        $_SESSION = [
            'auth' => [
                'user_id' => 'marvel-admin',
                'role' => 'admin',
                'email' => 'seguridadmarvel@gmail.com',
                'last_activity' => time(),
            ],
            'session_created_at' => time(),
            'session_ip_hash' => hash('sha256', '10.0.0.1'),
            'session_ua_hash' => hash('sha256', 'Original Agent'),
        ];

        $service = new AuthService(new SecurityConfig(), new SecurityLogger($this->logFile));

        // Cambia el UA
        $_SERVER['HTTP_USER_AGENT'] = 'Tampered Agent';

        self::assertFalse($service->isAuthenticated());
        $log = (string) file_get_contents($this->logFile);
        self::assertStringContainsString('session_hijack_detected', $log);
    }

    public function testChangingIpForcesLogout(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'Original Agent';

        $_SESSION = [
            'auth' => [
                'user_id' => 'marvel-admin',
                'role' => 'admin',
                'email' => 'seguridadmarvel@gmail.com',
                'last_activity' => time(),
            ],
            'session_created_at' => time(),
            'session_ip_hash' => hash('sha256', '10.0.0.1'),
            'session_ua_hash' => hash('sha256', 'Original Agent'),
        ];

        $service = new AuthService(new SecurityConfig(), new SecurityLogger($this->logFile));

        // Cambia la IP
        $_SERVER['REMOTE_ADDR'] = '10.0.0.2';

        self::assertFalse($service->isAuthenticated());
        $log = (string) file_get_contents($this->logFile);
        self::assertStringContainsString('session_hijack_detected', $log);
    }
}
