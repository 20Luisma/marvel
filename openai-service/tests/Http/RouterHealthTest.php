<?php

declare(strict_types=1);

namespace Creawebes\OpenAI\Tests\Http;

use PHPUnit\Framework\TestCase;
use Creawebes\OpenAI\Http\Router;

/**
 * Tests for the Router's /health endpoint and 404 handling.
 *
 * Verifies that the healthcheck endpoint responds correctly and that
 * unknown routes return the expected 404 response.
 */
final class RouterHealthTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();

        // Reset superglobals for a clean test state
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        unset($_SERVER['HTTP_ORIGIN']);
        unset($_SERVER['HTTP_X_INTERNAL_SIGNATURE']);
        unset($_SERVER['HTTP_X_INTERNAL_TIMESTAMP']);
        unset($_SERVER['HTTP_X_INTERNAL_CALLER']);
    }

    public function testHealthEndpointReturnsOk(): void
    {
        ob_start();
        $this->router->handle('GET', '/health');
        $output = ob_get_clean();

        $this->assertIsString($output);
        $decoded = json_decode((string) $output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('ok', $decoded['status'] ?? null);
        $this->assertSame('openai-service', $decoded['service'] ?? null);
        $this->assertArrayHasKey('time', $decoded);
    }

    public function testHealthEndpointIgnoresPostMethod(): void
    {
        ob_start();
        $this->router->handle('POST', '/health');
        $output = ob_get_clean();

        // POST to /health should result in 404 (only GET is mapped)
        $decoded = json_decode((string) $output, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('error', $decoded);
    }

    public function testUnknownRouteReturns404(): void
    {
        ob_start();
        $this->router->handle('GET', '/nonexistent/route');
        $output = ob_get_clean();

        $decoded = json_decode((string) $output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('Not Found', $decoded['error'] ?? null);
    }

    public function testUnknownPostRouteReturns404(): void
    {
        ob_start();
        $this->router->handle('POST', '/api/does-not-exist');
        $output = ob_get_clean();

        $decoded = json_decode((string) $output, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('error', $decoded);
    }
}
