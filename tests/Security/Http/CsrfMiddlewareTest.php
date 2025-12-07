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
        $this->assertStringContainsString('Invalid CSRF token', $output);
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
}
