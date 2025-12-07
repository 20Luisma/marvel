<?php

declare(strict_types=1);

namespace Tests\Application\Security;

use App\Application\Security\IpBlockerService;
use App\Application\Security\LoginAttemptService;
use App\Security\Logging\SecurityLogger;
use PHPUnit\Framework\TestCase;

final class IpBlockerServiceTest extends TestCase
{
    public function testCheckReturnsFalseWhenBlockedAndLogs(): void
    {
        $loginAttempts = $this->loginAttemptsWithFile($tempFile = $this->tempAttemptsFile());
        $this->writeAttempts($loginAttempts, 'user@example.com', '1.1.1.1', time() + 300);

        $logFile = sys_get_temp_dir() . '/security-log-' . uniqid('', true) . '.log';
        $logger = new SecurityLogger($logFile);

        $service = new IpBlockerService($loginAttempts, $logger);
        $result = $service->check('user@example.com', '1.1.1.1');

        self::assertFalse($result);
        $contents = is_file($logFile) ? file_get_contents($logFile) : '';
        self::assertNotFalse($contents);
        self::assertStringContainsString('event=login_blocked', (string) $contents);
        @unlink($logFile);
    }

    public function testCheckReturnsTrueWhenNotBlocked(): void
    {
        $loginAttempts = $this->loginAttemptsWithFile($tempFile = $this->tempAttemptsFile());

        $logFile = sys_get_temp_dir() . '/security-log-' . uniqid('', true) . '.log';
        $logger = new SecurityLogger($logFile);

        $service = new IpBlockerService($loginAttempts, $logger);
        self::assertTrue($service->check('user@example.com', '1.1.1.1'));
        $contents = is_file($logFile) ? file_get_contents($logFile) : '';
        self::assertTrue($contents === '' || $contents === false);
        @unlink($logFile);
        @unlink($tempFile);
    }

    private function tempAttemptsFile(): string
    {
        $file = sys_get_temp_dir() . '/login-attempts-' . uniqid('', true) . '.json';
        if (is_file($file)) {
            @unlink($file);
        }
        return $file;
    }

    private function loginAttemptsWithFile(string $file): LoginAttemptService
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $_SESSION = [];

        $service = new LoginAttemptService(null);
        $prop = new \ReflectionProperty(LoginAttemptService::class, 'filePath');
        $prop->setAccessible(true);
        $prop->setValue($service, $file);
        return $service;
    }

    private function writeAttempts(LoginAttemptService $service, string $email, string $ip, int $blockedUntil): void
    {
        $ref = new \ReflectionMethod(LoginAttemptService::class, 'key');
        $ref->setAccessible(true);
        $key = $ref->invoke($service, $email, $ip);

        $data = [
            $key => [
                // Más que MAX_ATTEMPTS para que cleanOld no reinicie el bloqueo.
                'attempts' => array_fill(0, 6, time()),
                'blocked_until' => $blockedUntil,
            ],
        ];

        $prop = new \ReflectionProperty(LoginAttemptService::class, 'filePath');
        $prop->setAccessible(true);
        $file = $prop->getValue($service);
        file_put_contents($file, json_encode($data));
    }
}
