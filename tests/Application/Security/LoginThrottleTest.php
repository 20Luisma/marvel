<?php

declare(strict_types=1);

namespace Tests\Application\Security;

use App\Application\Security\LoginAttemptService;
use App\Security\Logging\SecurityLogger;
use PHPUnit\Framework\TestCase;

final class LoginThrottleTest extends TestCase
{
    private string $attemptsFile;
    private string $logFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->attemptsFile = dirname(__DIR__, 2) . '/storage/security/login_attempts.json';
        $this->logFile = dirname(__DIR__, 2) . '/storage/logs/security_throttle_test.log';

        if (is_file($this->attemptsFile)) {
            @unlink($this->attemptsFile);
        }

        if (is_file($this->logFile)) {
            @unlink($this->logFile);
        }

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_URI'] = '/login';
        $_SERVER['X_TRACE_ID'] = 'throttle-test';
    }

    protected function tearDown(): void
    {
        if (is_file($this->attemptsFile)) {
            @unlink($this->attemptsFile);
        }

        if (is_file($this->logFile)) {
            @unlink($this->logFile);
        }

        parent::tearDown();
    }

    public function testBlocksAfterTooManyFailedAttempts(): void
    {
        $logger = new SecurityLogger($this->logFile);
        $service = new LoginAttemptService($logger);

        $email = 'user@example.com';
        $ip = '127.0.0.1';

        for ($i = 0; $i < 5; $i++) {
            $service->registerFailedAttempt($email, $ip);
        }

        self::assertSame(0, $service->getRemainingAttempts($email, $ip));
        self::assertFalse($service->isBlocked($email, $ip));

        $service->registerFailedAttempt($email, $ip); // sexto intento dispara bloqueo

        self::assertTrue($service->isBlocked($email, $ip));
        self::assertGreaterThan(0, $service->getBlockMinutesRemaining($email, $ip));
        self::assertFileExists($this->logFile);
        self::assertStringContainsString('login_blocked', (string) file_get_contents($this->logFile));
    }
}
