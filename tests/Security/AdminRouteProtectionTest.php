<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Application\Security\IpBlockerService;
use App\Application\Security\LoginAttemptService;
use App\Security\Auth\AuthService;
use App\Security\Http\AuthMiddleware;
use App\Security\Http\CsrfTokenManager;
use App\Security\Logging\SecurityLogger;
use PHPUnit\Framework\TestCase;
use Src\Shared\Http\Router;

final class AdminRouteProtectionTest extends TestCase
{
    private string $loginAttemptsFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAttemptsFile = dirname(__DIR__, 2) . '/storage/security/login_attempts.json';
        if (is_file($this->loginAttemptsFile)) {
            @unlink($this->loginAttemptsFile);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $_SESSION = [];
        header_remove();
        http_response_code(200);
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        parent::tearDown();
    }

    public function testSecretRouteRedirectsWhenNotAuthenticated(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'text/html';
        $_SERVER['REQUEST_URI'] = '/secret/sonar';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        ob_start();
        $this->router()->handle('GET', '/secret/sonar');
        ob_end_clean();

        $headers = headers_list();
        $location = array_values(array_filter($headers, static fn(string $header): bool => stripos($header, 'location:') === 0));

        self::assertSame('/secret/sonar', $_SESSION['redirect_to'] ?? null);
        if ($location !== []) {
            self::assertStringContainsString('/login', $location[0]);
        }
    }

    public function testSecretRouteReturnsForbiddenForNonAdmin(): void
    {
        session_start();
        $_SESSION['session_created_at'] = time();
        $_SESSION['auth'] = [
            'user_id' => 'user-123',
            'role' => 'user',
            'email' => 'user@example.com',
            'last_activity' => time(),
        ];
        $_SESSION['session_ip_hash'] = hash('sha256', '127.0.0.1');
        $_SESSION['session_ua_hash'] = hash('sha256', 'PHPUnit Agent');

        $_SERVER['HTTP_ACCEPT'] = 'text/html';
        $_SERVER['REQUEST_URI'] = '/secret/sonar';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit Agent';

        ob_start();
        $this->router()->handle('GET', '/secret/sonar');
        $output = ob_get_clean();

        self::assertStringContainsString('Acceso restringido', (string) $output);
    }

    public function testSecretRouteLoadsForAdmin(): void
    {
        session_start();
        $_SESSION['session_created_at'] = time();
        $_SESSION['auth'] = [
            'user_id' => 'marvel-admin',
            'role' => 'admin',
            'email' => 'seguridadmarvel@gmail.com',
            'last_activity' => time(),
        ];
        $_SESSION['session_ip_hash'] = hash('sha256', '127.0.0.1');
        $_SESSION['session_ua_hash'] = hash('sha256', 'PHPUnit Agent');

        $_SERVER['HTTP_ACCEPT'] = 'text/html';
        $_SERVER['REQUEST_URI'] = '/secret/sonar';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit Agent';

        ob_start();
        $this->router()->handle('GET', '/secret/sonar');
        $output = ob_get_clean();

        self::assertStringContainsString('Sonar', (string) $output);
    }

    private function router(): Router
    {
        $authService = new AuthService();
        $securityLogger = new SecurityLogger();
        $loginAttempts = new LoginAttemptService($securityLogger);
        $ipBlocker = new IpBlockerService($loginAttempts, $securityLogger);

        return new Router([
            'security' => [
                'auth' => $authService,
                'csrf' => new CsrfTokenManager('test'),
                'middleware' => new AuthMiddleware($authService),
                'ipBlocker' => $ipBlocker,
            ],
            'useCases' => [],
        ]);
    }
}
