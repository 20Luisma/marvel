<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Security\Csrf\CsrfService;
use App\Security\RateLimit\RateLimiter;
use App\Security\Validation\InputSanitizer;
use PHPUnit\Framework\TestCase;

final class SecuritySmokeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
        } else {
            @session_start();
            $_SESSION = [];
        }

        $storage = dirname(__DIR__, 2) . '/storage/rate_limit';
        if (is_dir($storage)) {
            foreach (glob($storage . '/*.json') ?: [] as $file) {
                @unlink($file);
            }
        }
    }

    /**
     * @group security
     */
    public function testInvalidCsrfTokenIsRejected(): void
    {
        CsrfService::generateToken();

        $this->assertFalse(CsrfService::validateToken('HACKED_123'));
    }

    /**
     * @group security
     */
    public function testSanitizerNeutralizesScriptTags(): void
    {
        $raw = '<script>alert(1)</script><img src=x onerror=alert(2)> ¿Quién es más fuerte, Hulk o Thor?';
        $sanitizer = new InputSanitizer();

        $sanitized = $sanitizer->sanitizeString($raw, 1000);

        $this->assertStringNotContainsString('<script', strtolower($sanitized));
        $this->assertStringNotContainsString('onerror', strtolower($sanitized));
        $this->assertStringContainsString('Quién es más fuerte', $sanitized);
    }

    /**
     * @group security
     */
    public function testRateLimiterBlocksAfterMaxAttempts(): void
    {
        $limiter = new RateLimiter(true, 3, 60);
        $ip = '127.0.0.1';
        $path = '/test-rate';

        $first = $limiter->hit($ip, $path);
        $second = $limiter->hit($ip, $path);
        $third = $limiter->hit($ip, $path);
        $fourth = $limiter->hit($ip, $path);

        $this->assertFalse($first->isLimited);
        $this->assertFalse($second->isLimited);
        $this->assertFalse($third->isLimited);
        $this->assertTrue($fourth->isLimited);
    }
}
