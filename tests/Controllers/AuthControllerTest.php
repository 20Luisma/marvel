<?php

declare(strict_types=1);

namespace Src\Controllers;

function header(string $header, bool $replace = true, int $response_code = 0): void {
    $GLOBALS['headers'][] = $header;
}

function http_response_code(int $code = 0): int|bool {
    if ($code !== 0) {
        $GLOBALS['response_code'] = $code;
    }
    return $GLOBALS['response_code'] ?? 200;
}

function session_status(): int {
    return PHP_SESSION_ACTIVE;
}

function session_start(): bool {
    return true;
}

namespace App\Security\Auth;

function password_verify(string $password, string $hash): bool {
    return $password === 'pass';
}

function session_regenerate_id(bool $delete_old_session = false): bool {
    return true;
}

function session_destroy(): bool {
    $_SESSION = [];
    return true;
}

function setcookie(string $name, string $value = "", array $options = []): bool {
    return true;
}

function session_name(): string {
    return 'PHPSESSID';
}

function session_get_cookie_params(): array {
    return [
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ];
}

namespace App\Security\Http;

function session_status(): int {
    return PHP_SESSION_ACTIVE;
}

function session_start(): bool {
    return true;
}

namespace App\Application\Security;

function session_status(): int {
    return PHP_SESSION_ACTIVE;
}

function session_start(): bool {
    return true;
}

namespace Tests\Controllers;

use Src\Controllers\AuthController;
use App\Security\Auth\AuthService;
use App\Security\Http\CsrfTokenManager;
use App\Application\Security\IpBlockerService;
use App\Application\Security\LoginAttemptService;
use App\Config\SecurityConfig;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 */
class AuthControllerTest extends TestCase
{
    private $authService;
    private $csrfManager;
    private $ipBlocker;
    private $controller;
    private $securityConfig;
    private $loginAttemptService;
    private $attemptsFile;

    protected function setUp(): void
    {
        putenv('ADMIN_EMAIL=user@example.com');
        putenv('ADMIN_PASSWORD_HASH=hash');

        $this->securityConfig = new SecurityConfig();
        $this->authService = new AuthService($this->securityConfig);
        
        // CsrfTokenManager
        $this->csrfManager = new CsrfTokenManager('local');
        
        // LoginAttemptService & IpBlockerService
        $this->loginAttemptService = new LoginAttemptService();
        $this->ipBlocker = new IpBlockerService($this->loginAttemptService);
        
        $this->controller = new AuthController(
            $this->authService,
            $this->csrfManager,
            $this->ipBlocker
        );
        
        $GLOBALS['headers'] = [];
        $GLOBALS['response_code'] = 200;
        $_SESSION = [];
        $_POST = [];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        
        // Setup CSRF token
        $_SESSION['_csrf_token'] = 'valid';
        
        // Setup attempts file path for cleanup
        $this->attemptsFile = __DIR__ . '/../../storage/security/login_attempts.json';
        if (file_exists($this->attemptsFile)) {
            unlink($this->attemptsFile);
        }
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['headers']);
        unset($GLOBALS['response_code']);
        if (file_exists($this->attemptsFile)) {
            unlink($this->attemptsFile);
        }
    }

    public function testLoginSuccess(): void
    {
        $_POST['_token'] = 'valid';
        $_POST['email'] = 'user@example.com';
        $_POST['password'] = 'pass';

        $this->controller->login();

        $this->assertContains('Location: /seccion', $GLOBALS['headers']);
        $this->assertTrue($this->authService->isAuthenticated());
    }

    public function testLoginFailed(): void
    {
        $_POST['_token'] = 'valid';
        $_POST['email'] = 'user@example.com';
        $_POST['password'] = 'wrong';

        $this->controller->login();

        $this->assertContains('Location: /login', $GLOBALS['headers']);
        $this->assertArrayHasKey('auth_error', $_SESSION);
        $this->assertFalse($this->authService->isAuthenticated());
    }
    
    public function testLoginBlocked(): void
    {
        $_POST['_token'] = 'valid';
        $_POST['email'] = 'user@example.com';
        $_POST['password'] = 'wrong';

        // Fail 5 times to block
        for ($i = 0; $i < 6; $i++) {
            $this->loginAttemptService->registerFailedAttempt('user@example.com', '127.0.0.1');
        }

        ob_start();
        $this->controller->login();
        $output = ob_get_clean();

        $this->assertEquals(429, $GLOBALS['response_code']);
        $this->assertJson($output);
    }

    public function testLoginFailsWithInvalidCsrf(): void
    {
        $_POST['_token'] = 'invalid';
        $_POST['email'] = 'user@example.com';
        $_POST['password'] = 'pass';

        $this->controller->login();

        $this->assertContains('Location: /login', $GLOBALS['headers']);
        $this->assertArrayHasKey('auth_error', $_SESSION);
        $this->assertStringContainsString('Token de seguridad inválido', $_SESSION['auth_error']);
    }

    public function testLogoutSuccess(): void
    {
        $_POST['_token'] = 'valid';
        
        $this->controller->logout();

        $this->assertContains('Location: /', $GLOBALS['headers']);
        $this->assertFalse($this->authService->isAuthenticated());
    }

    public function testLogoutFailsWithInvalidCsrf(): void
    {
        $_POST['_token'] = 'invalid';
        
        // Simulate logged in
        $this->authService->login('user@example.com', 'pass');
        
        $this->controller->logout();

        $this->assertContains('Location: /', $GLOBALS['headers']);
        // Should still be authenticated? The controller redirects but doesn't call logout() if token invalid.
        // Let's check implementation: yes, returns early.
        $this->assertTrue($this->authService->isAuthenticated());
    }
}
