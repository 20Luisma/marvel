<?php

declare(strict_types=1);

namespace Tests\Security\Http;

use App\Security\Http\CsrfMiddleware;
use App\Security\Http\CsrfTerminationException;
use App\Security\Logging\SecurityLogger;
use PHPUnit\Framework\TestCase;

final class CsrfMiddlewareTest extends TestCase
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
        
        // Partial mock to override terminate (prevents real termination)
        $this->middleware = $this->getMockBuilder(CsrfMiddleware::class)
            ->setConstructorArgs([$this->logger])
            ->onlyMethods(['terminate'])
            ->getMock();
            
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_POST = [];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
    }

    public function testHandleProtectedPathSuccess(): void
    {
        $_SESSION['_csrf_token'] = 'valid_token';
        $_POST['csrf_token'] = 'valid_token';
        
        $this->middleware->expects($this->never())->method('terminate');
        
        $result = $this->middleware->handle('/login');
        
        $this->assertTrue($result);
    }

    public function testHandleProtectedPathFailure(): void
    {
        $_SESSION['_csrf_token'] = 'valid_token';
        $_POST['csrf_token'] = 'invalid_token';
        
        // terminate() is called once — that's the key assertion for CSRF failure
        $this->middleware->expects($this->once())->method('terminate');
        
        ob_start();
        $result = $this->middleware->handle('/login');
        $output = ob_get_clean();
        
        $this->assertFalse($result);
        $this->assertStringContainsString('Token CSRF', $output);
    }

    public function testHandleUnprotectedPath(): void
    {
        $this->middleware->expects($this->never())->method('terminate');
        
        $result = $this->middleware->handle('/public');
        
        $this->assertTrue($result);
    }
    
    public function testHandleGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        $this->middleware->expects($this->never())->method('terminate');
        
        $result = $this->middleware->handle('/login');
        
        $this->assertTrue($result);
    }

    public function testHandleWithTokenFromHeader(): void
    {
        $_SESSION['_csrf_token'] = 'valid_header_token';
        $_POST = [];
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_header_token';
        
        $this->middleware->expects($this->never())->method('terminate');
        
        $result = $this->middleware->handle('/login');
        
        $this->assertTrue($result);
    }

    public function testHandleWithTokenFromAltField(): void
    {
        $_SESSION['_csrf_token'] = 'valid_alt_token';
        $_POST['_token'] = 'valid_alt_token';
        
        $this->middleware->expects($this->never())->method('terminate');
        
        $result = $this->middleware->handle('/login');
        
        $this->assertTrue($result);
    }

    public function testHandleFailureWithMissingToken(): void
    {
        $_SESSION['_csrf_token'] = 'valid_token';
        $_POST = [];
        
        $this->middleware->expects($this->once())->method('terminate');
        
        ob_start();
        $result = $this->middleware->handle('/login');
        ob_get_clean();
        
        $this->assertFalse($result);
    }

    public function testHandleLogsWhenTokenPresentButInvalid(): void
    {
        $_SESSION['_csrf_token'] = 'expected_token';
        $_POST['csrf_token'] = 'wrong_token_value';
        
        $this->middleware->expects($this->once())->method('terminate');
        
        ob_start();
        $result = $this->middleware->handle('/login');
        ob_get_clean();
        
        $this->assertFalse($result);
        
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
        $result = $middlewareNoLogger->handle('/login');
        ob_get_clean();
        
        $this->assertFalse($result);
    }

    public function testHandleProtectedApiRoute(): void
    {
        $_SESSION['_csrf_token'] = 'api_token';
        $_POST['csrf_token'] = 'api_token';
        
        $this->middleware->expects($this->never())->method('terminate');
        
        $result = $this->middleware->handle('/api/rag/heroes');
        
        $this->assertTrue($result);
    }
}
