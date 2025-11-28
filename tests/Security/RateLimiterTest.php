<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Security\RateLimit\RateLimiter;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    private string $storageDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storageDir = dirname(__DIR__, 2) . '/storage/rate_limit';
        if (is_dir($this->storageDir)) {
            array_map('unlink', glob($this->storageDir . '/*.json') ?: []);
        }
    }

    public function testRespectsLimitAndBlocksWhenExceeded(): void
    {
        $limiter = new RateLimiter(true, 2, 60);
        $ip = '127.0.0.1';
        $path = '/login';

        $result1 = $limiter->hit($ip, $path);
        $result2 = $limiter->hit($ip, $path);
        $result3 = $limiter->hit($ip, $path);

        self::assertFalse($result1->isLimited);
        self::assertFalse($result2->isLimited);
        self::assertTrue($result3->isLimited);
        self::assertSame(0, $result3->remaining);
    }

    public function testResetsWindowAfterExpiry(): void
    {
        $limiter = new RateLimiter(true, 1, 1);
        $ip = '127.0.0.2';
        $path = '/agentia';

        $first = $limiter->hit($ip, $path);
        self::assertFalse($first->isLimited);

        // Simular expiración forzando reset_at en el archivo.
        $file = $this->storageDir . '/' . hash('sha256', $ip . '|' . $path) . '.json';
        $state = ['count' => 0, 'reset_at' => time() - 1];
        file_put_contents($file, json_encode($state));

        $second = $limiter->hit($ip, $path);
        self::assertFalse($second->isLimited);
    }
}
