<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Security\Session\SessionReplayMonitor;
use App\Security\Logging\SecurityLogger;
use PHPUnit\Framework\TestCase;

final class SessionReplayMonitorTest extends TestCase
{
    private string $logFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logFile = dirname(__DIR__, 2) . '/storage/logs/session_replay_monitor_test.log';
        if (is_file($this->logFile)) {
            @unlink($this->logFile);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_id('sess-replay-' . uniqid());
        session_start();
        $_SESSION = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'ReplayMonitorTest Agent';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['X_TRACE_ID'] = 'trace-replay-test';
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        parent::tearDown();
    }

    public function testInitReplayTokenStoresToken(): void
    {
        $monitor = new SessionReplayMonitor(new SecurityLogger($this->logFile));
        $monitor->initReplayToken();

        self::assertArrayHasKey('security_replay_token', $_SESSION);
        self::assertNotEmpty($_SESSION['security_replay_token']);

        $log = (string) file_get_contents($this->logFile);
        self::assertStringContainsString('session_replay_token_issued', $log);
    }

    public function testDetectReplayDoesNotBreakFlow(): void
    {
        $monitor = new SessionReplayMonitor(new SecurityLogger($this->logFile));
        $monitor->detectReplayAttack();

        self::assertTrue(true, 'No exceptions and flow continues.');
    }

    public function testDetectsUserAgentChangeOnPost(): void
    {
        $monitor = new SessionReplayMonitor(new SecurityLogger($this->logFile));
        $monitor->initReplayToken();

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_USER_AGENT'] = 'Tampered Agent';

        $monitor->detectReplayAttack();

        $log = (string) file_get_contents($this->logFile);
        self::assertStringContainsString('session_replay_suspected', $log);
        self::assertStringContainsString('user_agent_mismatch', $log);
    }

    public function testDetectsSessionIdChange(): void
    {
        $monitor = new SessionReplayMonitor(new SecurityLogger($this->logFile));
        $monitor->initReplayToken();

        // Simular cambio de SID
        $_SESSION['security_replay_sid'] = 'different-sid';
        $monitor->detectReplayAttack();

        $log = (string) file_get_contents($this->logFile);
        self::assertStringContainsString('session_replay_suspected', $log);
        self::assertStringContainsString('session_id_changed', $log);
    }
}
