<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Application\Security\LoginAttemptService;
use App\Security\Logging\SecurityLogger;
use PHPUnit\Framework\TestCase;

final class LoginAttemptServiceTest extends TestCase
{
    private string $storageFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storageFile = dirname(__DIR__, 2) . '/storage/security/login_attempts.json';
        $dir = dirname($this->storageFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (is_file($this->storageFile)) {
            @unlink($this->storageFile);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
        } else {
            @session_start();
            $_SESSION = [];
        }
    }

    /**
     * @group security
     */
    public function testBlocksAfterFiveFailedAttempts(): void
    {
        $service = new LoginAttemptService(new SecurityLogger());
        $email = 'user@example.com';
        $ip = '127.0.0.1';

        for ($i = 0; $i < 5; $i++) {
            $service->registerFailedAttempt($email, $ip);
        }
        $this->assertFalse($service->isBlocked($email, $ip), 'Should not block at limit threshold');

        $service->registerFailedAttempt($email, $ip); // sexto intento
        $this->assertTrue($service->isBlocked($email, $ip));
        $this->assertSame(0, $service->getRemainingAttempts($email, $ip));
    }

    /**
     * @group security
     */
    public function testClearsAttemptsOnSuccess(): void
    {
        $service = new LoginAttemptService(new SecurityLogger());
        $email = 'user@example.com';
        $ip = '127.0.0.2';

        $service->registerFailedAttempt($email, $ip);
        $this->assertFalse($service->isBlocked($email, $ip));

        $service->clearAttempts($email, $ip);
        $this->assertFalse($service->isBlocked($email, $ip));
        $this->assertSame(5, $service->getRemainingAttempts($email, $ip));
    }
}
