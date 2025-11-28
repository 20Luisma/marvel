<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Security\Auth\AuthService;
use PHPUnit\Framework\TestCase;

final class AuthServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $_SESSION = [];
        header_remove();
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        parent::tearDown();
    }

    public function testLoginRegeneratesSessionIdOnSuccess(): void
    {
        session_id('testsessid123');
        session_start();
        $authService = new AuthService();

        $initialId = session_id();
        $result = $authService->login('seguridadmarvel@gmail.com', 'seguridadmarvel2025');
        $newId = session_id();

        self::assertTrue($result);
        self::assertNotSame($initialId, $newId);
        self::assertSame('marvel-admin', $_SESSION['auth']['user_id'] ?? null);
    }
}
