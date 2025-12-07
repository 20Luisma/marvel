<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Security\Http\RateLimitMiddleware;
use App\Security\RateLimit\RateLimiter;
use PHPUnit\Framework\TestCase;

final class RateLimitMiddlewareTest extends TestCase
{
    private string $storageDir;
    private string $logFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storageDir = dirname(__DIR__, 2) . '/storage/rate_limit';
        $this->logFile = dirname(__DIR__, 2) . '/storage/logs/security.log';

        if (is_dir($this->storageDir)) {
            array_map('unlink', glob($this->storageDir . '/*.json') ?: []);
        }

        if (is_file($this->logFile)) {
            @unlink($this->logFile);
        }

        $_SERVER['X_TRACE_ID'] = 'test-trace';
    }

    public function testBlocksApiPathWithJsonResponse(): void
    {
        $limiter = new RateLimiter(true, 1, 60, ['/api/rag/heroes' => ['max' => 1, 'window' => 60]]);
        $middleware = new RateLimitMiddleware($limiter, ['/api/rag/heroes' => ['max' => 1, 'window' => 60]], new \App\Security\Logging\SecurityLogger($this->logFile));
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';

        $limiter->hit('10.0.0.1', '/api/rag/heroes'); // consume cuota

        ob_start();
        $result = $middleware->handle('POST', '/api/rag/heroes');
        $output = ob_get_clean();

        self::assertFalse($result);
        self::assertIsString($output);
        self::assertStringContainsString('"error":"rate_limited"', $output);
        self::assertFileExists($this->logFile);
    }

    public function testBlocksHtmlPathWithHtmlResponse(): void
    {
        $limiter = new RateLimiter(true, 1, 60, ['/agentia' => ['max' => 1, 'window' => 60]]);
        $middleware = new RateLimitMiddleware($limiter, ['/agentia' => ['max' => 1, 'window' => 60]], new \App\Security\Logging\SecurityLogger($this->logFile));
        $_SERVER['REMOTE_ADDR'] = '10.0.0.2';
        $_SERVER['HTTP_ACCEPT'] = 'text/html';

        $limiter->hit('10.0.0.2', '/agentia'); // consume cuota

        ob_start();
        $result = $middleware->handle('GET', '/agentia');
        $output = ob_get_clean();

        self::assertFalse($result);
        self::assertIsString($output);
        self::assertStringContainsString('Demasiadas peticiones', $output);
    }
}
