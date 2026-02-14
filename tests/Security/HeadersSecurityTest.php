<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\TestCase;
use App\Shared\Http\Router;
use App\Security\Http\SecurityHeaders;
use App\Controllers\Http\Request;
final class HeadersSecurityTest extends TestCase
{
    private string $root;
    /** @var array<string, mixed>|null */
    private static ?array $bootstrapContainer = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__, 2);
        $this->resetGlobals();
    }

    protected function tearDown(): void
    {
        $this->resetGlobals();
        parent::tearDown();
    }

    public function testHomeHeaders(): void
    {
        $result = $this->dispatch('GET', '/');
        $this->assertSecurityHeaders($result['headers']);
    }

    public function testLoginHeaders(): void
    {
        $result = $this->dispatch('GET', '/login');
        $this->assertSecurityHeaders($result['headers']);
    }

    public function testSeccionHeaders(): void
    {
        $result = $this->dispatch('GET', '/seccion', true);
        $this->assertSecurityHeaders($result['headers']);
    }

    public function testSecretSonarHeaders(): void
    {
        $result = $this->dispatch('GET', '/secret/sonar', true);
        $this->assertSecurityHeaders($result['headers']);
    }

    public function testApiRagHeroesHeaders(): void
    {
        Request::withJsonBody(json_encode(['query' => 'spider-man'], JSON_THROW_ON_ERROR));
        $result = $this->dispatch('POST', '/api/rag/heroes', true);
        $this->assertSecurityHeaders($result['headers']);
    }

    /**
     * @param list<non-empty-string> $headers
     */
    private function assertSecurityHeaders(array $headers): void
    {
        $map = $this->headersToMap($headers);

        self::assertArrayHasKey('x-frame-options', $map);
        self::assertSame('SAMEORIGIN', strtoupper($map['x-frame-options'] ?? ''));

        self::assertArrayHasKey('x-content-type-options', $map);
        self::assertSame('nosniff', strtolower($map['x-content-type-options'] ?? ''));

        self::assertArrayHasKey('referrer-policy', $map);
        self::assertStringContainsString('no-referrer-when-downgrade', strtolower($map['referrer-policy'] ?? ''));

        self::assertArrayHasKey('permissions-policy', $map);
        self::assertNotEmpty($map['permissions-policy']);

        self::assertArrayHasKey('content-security-policy', $map);
        $csp = $map['content-security-policy'] ?? '';
        self::assertStringContainsString("default-src 'self'", $csp);

        self::assertArrayHasKey('cross-origin-resource-policy', $map);
        self::assertArrayHasKey('cross-origin-opener-policy', $map);
        self::assertArrayHasKey('cross-origin-embedder-policy', $map);
        self::assertArrayHasKey('x-download-options', $map);
        self::assertArrayHasKey('x-permitted-cross-domain-policies', $map);

        // CSP básica: default-src 'self', se permite 'unsafe-inline' en script-src por compatibilidad actual.
        if ($csp !== '') {
            $this->assertCsp($csp);
        }

        $this->assertSessionCookieFlags($headers);
    }

    private function assertCsp(string $csp): void
    {
        self::assertStringContainsString("default-src 'self'", $csp);
        // Se permite unsafe-inline O nonces en script-src. Con nonces es más seguro.
        if (str_contains($csp, 'script-src')) {
            self::assertStringContainsString("script-src 'self'", $csp);
            // Acepta tanto unsafe-inline (backward compat) como nonces (CSP estricta)
            $hasUnsafeInline = str_contains($csp, "'unsafe-inline'");
            $hasNonce = (bool) preg_match("/'nonce-[A-Za-z0-9+\/=]+'/", $csp);
            self::assertTrue(
                $hasUnsafeInline || $hasNonce,
                "CSP debe contener 'unsafe-inline' o nonces para script-src"
            );
        }
    }

    /**
     * @param list<non-empty-string> $headers
     */
    private function assertSessionCookieFlags(array $headers): void
    {
        $cookieHeaders = array_filter($headers, static fn(string $header): bool => stripos($header, 'Set-Cookie:') === 0);
        $sessionCookies = array_filter($cookieHeaders, static fn(string $header): bool => str_contains($header, 'PHPSESSID'));

        if ($sessionCookies === []) {
            return;
        }

        $cookieLine = reset($sessionCookies);
        self::assertStringContainsStringIgnoringCase('httponly', (string) $cookieLine);
        self::assertTrue(
            str_contains(strtolower((string) $cookieLine), 'samesite=lax')
            || str_contains(strtolower((string) $cookieLine), 'samesite=strict'),
            'SameSite must be Lax or Strict'
        );

        if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1')) {
            self::assertStringContainsStringIgnoringCase('secure', (string) $cookieLine);
        }
    }

    /**
     * @param list<non-empty-string> $headers
     * @return array<string, string>
     */
    private function headersToMap(array $headers): array
    {
        if ($headers === [] && isset($GLOBALS['__test_headers']) && is_array($GLOBALS['__test_headers'])) {
            $headers = $GLOBALS['__test_headers'];
        }

        $map = [];
        foreach ($headers as $header) {
            $parts = explode(':', $header, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $key = strtolower(trim($parts[0]));
            $value = trim($parts[1]);
            $map[$key] = $value;
        }

        return $map;
    }

    /**
     * @return array{headers: list<non-empty-string>, output: string, status: int}
     */
    private function dispatch(string $method, string $path, bool $asAdmin = false): array
    {
        $this->resetGlobals();
        $this->resetSecurityHeadersFlag();
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $path;
        $_SERVER['HTTP_ACCEPT'] = $method === 'POST' ? 'application/json' : 'text/html';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit Headers Security';
        $_ENV['APP_ENV'] = 'test';
        putenv('APP_ENV=test');
        // Fuerza uso de SQLite en memoria para evitar conexiones externas en pruebas.
        putenv('DB_DSN=sqlite::memory:');
        putenv('DB_USER=');
        putenv('DB_PASSWORD=');
        $_ENV['DB_DSN'] = 'sqlite::memory:';
        $_ENV['DB_USER'] = '';
        $_ENV['DB_PASSWORD'] = '';

        SecurityHeaders::apply();

        if (self::$bootstrapContainer === null) {
            self::$bootstrapContainer = require $this->root . '/src/bootstrap.php';
        }
        $container = self::$bootstrapContainer;

        if ($asAdmin) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['session_created_at'] = time();
            $_SESSION['auth'] = [
                'user_id' => 'marvel-admin',
                'role' => 'admin',
                'email' => 'seguridadmarvel@gmail.com',
                'last_activity' => time(),
            ];
            $_SESSION['session_ip_hash'] = hash('sha256', $_SERVER['REMOTE_ADDR']);
            $_SESSION['session_ua_hash'] = hash('sha256', $_SERVER['HTTP_USER_AGENT']);
        }

        ob_start();
        try {
            (new Router($container))->handle($method, $path);
        } catch (\App\Security\Http\CsrfTerminationException) {
            // CSRF middleware throws CsrfTerminationException on validation failure
        }
        $output = (string) ob_get_clean();
        $headers = headers_list();
        $statusCode = http_response_code();
        $status = is_int($statusCode) ? $statusCode : 200;

        return [
            'headers' => $headers,
            'output' => $output,
            'status' => $status,
        ];
    }

    private function resetSecurityHeadersFlag(): void
    {
        if (!class_exists(\App\Security\Http\SecurityHeaders::class)) {
            return;
        }

        $ref = new \ReflectionClass(\App\Security\Http\SecurityHeaders::class);
        if ($ref->hasProperty('applied')) {
            $prop = $ref->getProperty('applied');
            $prop->setAccessible(true);
            $prop->setValue(null, false);
        }
    }

    private function resetGlobals(): void
    {
        header_remove();
        http_response_code(200);
        $_GET = [];
        $_POST = [];
        $_SERVER = [];
        $_SESSION = [];
        Request::withJsonBody('');
    }
}
