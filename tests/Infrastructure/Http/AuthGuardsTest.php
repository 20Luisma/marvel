<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

// Mock functions en el namespace del SUT
if (!function_exists('App\Infrastructure\Http\header')) {
    function header(string $header, bool $replace = true, int $response_code = 0): void {
        $GLOBALS['test_headers'][] = $header;
        if ($response_code !== 0) {
            $GLOBALS['test_response_code'] = $response_code;
        }
    }
}

if (!function_exists('App\Infrastructure\Http\http_response_code')) {
    function http_response_code(?int $code = null): int|bool {
        if ($code !== null) {
            $GLOBALS['test_response_code'] = $code;
        }
        return $GLOBALS['test_response_code'] ?? 200;
    }
}

namespace Tests\Infrastructure\Http;

use App\Infrastructure\Http\AuthGuards;
use PHPUnit\Framework\TestCase;

final class AuthGuardsTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['test_headers'] = [];
        $GLOBALS['test_response_code'] = 200;
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['test_headers']);
        unset($GLOBALS['test_response_code']);
        $_SESSION = [];
    }

    public function testSessionUserReturnsEmptyWhenNoAuth(): void
    {
        $_SESSION = [];
        
        // Usamos reflection para acceder al método privado
        $reflection = new \ReflectionClass(AuthGuards::class);
        $method = $reflection->getMethod('sessionUser');
        $method->setAccessible(true);
        
        $result = $method->invoke(null);
        
        $this->assertNull($result['user_id'] ?? null);
        $this->assertNull($result['user_email'] ?? null);
        $this->assertNull($result['user_role'] ?? null);
    }

    public function testSessionUserReturnsDataFromDirectSession(): void
    {
        $_SESSION = [
            'user_id' => 'user123',
            'user_email' => 'test@example.com',
            'user_role' => 'admin',
        ];
        
        $reflection = new \ReflectionClass(AuthGuards::class);
        $method = $reflection->getMethod('sessionUser');
        $method->setAccessible(true);
        
        $result = $method->invoke(null);
        
        $this->assertSame('user123', $result['user_id']);
        $this->assertSame('test@example.com', $result['user_email']);
        $this->assertSame('admin', $result['user_role']);
    }

    public function testSessionUserReturnsDataFromAuthArray(): void
    {
        $_SESSION = [
            'auth' => [
                'user_id' => 'auth_user',
                'email' => 'auth@example.com',
                'role' => 'editor',
            ],
        ];
        
        $reflection = new \ReflectionClass(AuthGuards::class);
        $method = $reflection->getMethod('sessionUser');
        $method->setAccessible(true);
        
        $result = $method->invoke(null);
        
        $this->assertSame('auth_user', $result['user_id']);
        $this->assertSame('auth@example.com', $result['user_email']);
        $this->assertSame('editor', $result['user_role']);
    }

    public function testSessionUserPrioritizesDirectSessionOverAuth(): void
    {
        $_SESSION = [
            'user_id' => 'direct_user',
            'user_email' => 'direct@example.com',
            'user_role' => 'admin',
            'auth' => [
                'user_id' => 'auth_user',
                'email' => 'auth@example.com',
                'role' => 'editor',
            ],
        ];
        
        $reflection = new \ReflectionClass(AuthGuards::class);
        $method = $reflection->getMethod('sessionUser');
        $method->setAccessible(true);
        
        $result = $method->invoke(null);
        
        $this->assertSame('direct_user', $result['user_id']);
        $this->assertSame('direct@example.com', $result['user_email']);
        $this->assertSame('admin', $result['user_role']);
    }

    public function testSessionUserFallsBackToAuthWhenDirectIsNull(): void
    {
        $_SESSION = [
            'user_id' => null,
            'user_email' => null,
            'user_role' => null,
            'auth' => [
                'user_id' => 'fallback_user',
                'email' => 'fallback@example.com',
                'role' => 'viewer',
            ],
        ];
        
        $reflection = new \ReflectionClass(AuthGuards::class);
        $method = $reflection->getMethod('sessionUser');
        $method->setAccessible(true);
        
        $result = $method->invoke(null);
        
        $this->assertSame('fallback_user', $result['user_id']);
        $this->assertSame('fallback@example.com', $result['user_email']);
        $this->assertSame('viewer', $result['user_role']);
    }

    public function testSessionUserHandlesNonArrayAuth(): void
    {
        $_SESSION = [
            'auth' => 'not_an_array',
        ];
        
        $reflection = new \ReflectionClass(AuthGuards::class);
        $method = $reflection->getMethod('sessionUser');
        $method->setAccessible(true);
        
        $result = $method->invoke(null);
        
        $this->assertNull($result['user_id'] ?? null);
    }
}
