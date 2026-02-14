<?php

declare(strict_types=1);

namespace App\Security\Http;

if (!function_exists('App\Security\Http\http_response_code')) {
    function http_response_code(int $code = 0): int|bool {
        if ($code !== 0) {
            $GLOBALS['response_code'] = $code;
        }
        return $GLOBALS['response_code'] ?? 200;
    }
}

if (!function_exists('App\Security\Http\header')) {
    function header(string $header, bool $replace = true, int $response_code = 0): void {
        $GLOBALS['headers'][] = $header;
    }
}

namespace Tests\Security\Http;

use App\Security\Http\CsrfMiddleware;
use App\Security\Logging\SecurityLogger;
use PHPUnit\Framework\TestCase;

/**
 * @group no-coverage
 */
class CsrfMiddlewareTest extends TestCase
{
    private $logger;
    private $middleware;
    private $logFile;

    protected function setUp(): void
    {
        $this->logFile = __DIR__ . '/../../storage/logs/test_security_csrf.log';
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
        
        $this->logger = new SecurityLogger($this->logFile);
        
        // Partial mock to override terminate
        $this->middleware = $this->getMockBuilder(CsrfMiddleware::class)
            ->setConstructorArgs([$this->logger])
            ->onlyMethods(['terminate'])
            ->getMock();
            
        $GLOBALS['headers'] = [];
        $GLOBALS['response_code'] = 200;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_POST = [];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['headers']);
        unset($GLOBALS['response_code']);
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
    }

    public function testHandleProtectedPathSuccess(): void
    {
        $_SESSION['_csrf_token'] = 'valid_token';
        $_POST['csrf_token'] = 'valid_token';
        
        $this->middleware->expects($this->never())->method('terminate');
        
        $this->middleware->handle('/login');
        
        $this->assertEquals(200, $GLOBALS['response_code']);
    }

    public function testHandleProtectedPathFailure(): void
    {
        $_SESSION['_csrf_token'] = 'valid_token';
        $_POST['csrf_token'] = 'invalid_token';
        
        $this->middleware->expects($this->once())->method('terminate');
        
        ob_start();
        $this->middleware->handle('/login');
        $output = ob_get_clean();
        
        $this->assertEquals(403, $GLOBALS['response_code']);
        $this->assertStringContainsString('Token CSRF inválido o ausente', $output);
    }

    public function testHandleUnprotectedPath(): void
    {
        $this->middleware->expects($this->never())->method('terminate');
        
        $this->middleware->handle('/public');
        
        $this->assertEquals(200, $GLOBALS['response_code']);
    }
    
    public function testHandleGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        $this->middleware->expects($this->never())->method('terminate');
        
        $this->middleware->handle('/login');
        
        $this->assertEquals(200, $GLOBALS['response_code']);
    }

    public function testHandleWithTokenFromHeader(): void
    {
        $_SESSION['_csrf_token'] = 'valid_header_token';
        $_POST = [];
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_header_token';
        
        $this->middleware->expects($this->never())->method('terminate');
        
        $this->middleware->handle('/login');
        
        $this->assertEquals(200, $GLOBALS['response_code']);
    }

    public function testHandleWithTokenFromAltField(): void
    {
        $_SESSION['_csrf_token'] = 'valid_alt_token';
        $_POST['_token'] = 'valid_alt_token';
        
        $this->middleware->expects($this->never())->method('terminate');
        
        $this->middleware->handle('/login');
        
        $this->assertEquals(200, $GLOBALS['response_code']);
    }

    public function testHandleFailureWithMissingToken(): void
    {
        $_SESSION['_csrf_token'] = 'valid_token';
        $_POST = [];
        
        $this->middleware->expects($this->once())->method('terminate');
        
        ob_start();
        $this->middleware->handle('/login');
        ob_get_clean();
        
        $this->assertEquals(403, $GLOBALS['response_code']);
    }

    public function testHandleLogsWhenTokenPresentButInvalid(): void
    {
        $_SESSION['_csrf_token'] = 'expected_token';
        $_POST['csrf_token'] = 'wrong_token_value';
        
        $this->middleware->expects($this->once())->method('terminate');
        
        ob_start();
        $this->middleware->handle('/login');
        ob_get_clean();
        
        $this->assertEquals(403, $GLOBALS['response_code']);
        
        // Verify log was created
        $this->assertFileExists($this->logFile);
        $logContent = file_get_contents($this->logFile);
        $this->assertStringContainsString('csrf_failed', $logContent);
        $this->assertStringContainsString('token_state=present', $logContent);
    }

    public function testHandleLogsWhenNoLogger(): void
    {
        // Create middleware without logger
        $middlewareNoLogger = $this->getMockBuilder(CsrfMiddleware::class)
            ->setConstructorArgs([null])
            ->onlyMethods(['terminate'])
            ->getMock();
        
        $_SESSION['_csrf_token'] = 'expected_token';
        $_POST = [];
        
        $middlewareNoLogger->expects($this->once())->method('terminate');
        
        ob_start();
        $middlewareNoLogger->handle('/login');
        ob_get_clean();
        
        $this->assertEquals(403, $GLOBALS['response_code']);
    }

    public function testHandleProtectedApiRoute(): void
    {
        $_SESSION['_csrf_token'] = 'api_token';
        $_POST['csrf_token'] = 'api_token';
        
        $this->middleware->expects($this->never())->method('terminate');
        
        $this->middleware->handle('/api/rag/heroes');
        
        $this->assertEquals(200, $GLOBALS['response_code']);
    }
}
