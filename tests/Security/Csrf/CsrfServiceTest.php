<?php

declare(strict_types=1);

namespace Tests\Security\Csrf;

use App\Security\Csrf\CsrfService;
use PHPUnit\Framework\TestCase;

final class CsrfServiceTest extends TestCase
{
    protected function setUp(): void
    {
        // Ensure session is started for tests
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        // Clear any existing tokens
        unset($_SESSION['_csrf_token'], $_SESSION['csrf_token']);
    }

    protected function tearDown(): void
    {
        // Clear tokens after tests
        unset($_SESSION['_csrf_token'], $_SESSION['csrf_token']);
    }

    public function test_generate_token_creates_new_token_when_none_exists(): void
    {
        unset($_SESSION['_csrf_token'], $_SESSION['csrf_token']);

        $token = CsrfService::generateToken();

        $this->assertNotEmpty($token);
        $this->assertSame(64, strlen($token)); // 32 bytes = 64 hex chars
    }

    public function test_generate_token_returns_existing_token(): void
    {
        $firstToken = CsrfService::generateToken();
        $secondToken = CsrfService::generateToken();

        $this->assertSame($firstToken, $secondToken);
    }

    public function test_generate_token_sets_both_session_keys(): void
    {
        $token = CsrfService::generateToken();

        $this->assertSame($token, $_SESSION['_csrf_token']);
        $this->assertSame($token, $_SESSION['csrf_token']);
    }

    public function test_validate_token_returns_true_for_valid_token(): void
    {
        $token = CsrfService::generateToken();

        $result = CsrfService::validateToken($token);

        $this->assertTrue($result);
    }

    public function test_validate_token_returns_false_for_null_token(): void
    {
        CsrfService::generateToken();

        $result = CsrfService::validateToken(null);

        $this->assertFalse($result);
    }

    public function test_validate_token_returns_false_for_empty_token(): void
    {
        CsrfService::generateToken();

        $result = CsrfService::validateToken('');

        $this->assertFalse($result);
    }

    public function test_validate_token_returns_false_for_wrong_token(): void
    {
        CsrfService::generateToken();

        $result = CsrfService::validateToken('wrong-token-value');

        $this->assertFalse($result);
    }

    public function test_validate_token_returns_false_when_no_stored_token(): void
    {
        unset($_SESSION['_csrf_token'], $_SESSION['csrf_token']);

        $result = CsrfService::validateToken('some-token');

        $this->assertFalse($result);
    }

    public function test_validate_token_uses_fallback_alt_session_key(): void
    {
        // Only set the alt key
        unset($_SESSION['_csrf_token']);
        $_SESSION['csrf_token'] = 'fallback-token-value';

        $result = CsrfService::validateToken('fallback-token-value');

        $this->assertTrue($result);
    }
}
